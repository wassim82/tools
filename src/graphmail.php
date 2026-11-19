<?php
namespace madtec\tools;

/**
 * Envoi de mails via Microsoft Graph (client credentials), sans SMTP.
 *
 * $gm = new \madtec\tools\graphmail($tenant, $clientid, $secret, 'noreply@domaine.be');
 * $gm->send('client@exemple.be', 'Sujet', '<p>Bonjour</p>');
 * $gm->send(['a@x.be', 'b@x.be'], 'Facture', $html, [['name' => 'facture.pdf', 'content' => $pdf, 'type' => 'application/pdf']]);
 * $gm->send('client@exemple.be', 'Sujet', $html, [], [], null, false, 'rsf@domaine.be');   // autre boîte expéditrice
 *
 * Image intégrée : [['name' => 'banner.jpg', 'content' => $jpg, 'type' => 'image/jpeg', 'cid' => 'feedbann']] puis <img src="cid:feedbann">
 *
 * Lecture (droit Mail.Read ou Mail.ReadWrite sur la boîte) :
 * $msgs = $gm->messages('inbox', 'isRead eq false');
 * $msgs = $gm->messagesAll('inbox', 'receivedDateTime ge 2024-01-01T00:00:00Z', 2000);   // avec pagination
 * $txt  = $gm->bodies(['id1', 'id2']);                // texte de plusieurs messages en un appel ($batch)
 * Les identifiants renvoyés par messagesAll() sont immuables : ils ne changent pas quand le message change de dossier.
 * $gm->markread($msgs[0]['id']);                     // Mail.ReadWrite (false en 2e paramètre = non lu)
 * $gm->move($msgs[0]['id'], 'deleteditems');         // Mail.ReadWrite
 *
 * Le nom affiché est TOUJOURS celui de la boîte dans Exchange (Graph ignore un nom fourni)
 */
class graphmail
{
	private $tenant;
	private $clientid;
	private $secret;
	private $from;
	private $proxy;
	private $token = null;
	private $tokenexp = 0;

	public function __construct($tenant, $clientid, $secret, $from, $proxy = null)
	{
		$this->tenant   = (string) $tenant;
		$this->clientid = (string) $clientid;
		$this->secret   = (string) $secret;
		$this->from     = (string) $from;
		$this->proxy    = $proxy;
	}

	private function http($timeout)
	{
		$opt = array('timeout' => $timeout);
		if (!empty($this->proxy))
		{
			$opt['proxy'] = $this->proxy;
		}
		return new \GuzzleHttp\Client($opt);
	}

	// Jeton valable environ 1 h, réutilisé tant que l'objet existe
	private function token()
	{
		if ($this->token && time() < $this->tokenexp - 60)
		{
			return $this->token;
		}

		$rep = $this->http(15)->post('https://login.microsoftonline.com/'.rawurlencode($this->tenant).'/oauth2/v2.0/token', array(
			'form_params' => array(
				'client_id'     => $this->clientid,
				'client_secret' => $this->secret,
				'scope'         => 'https://graph.microsoft.com/.default',
				'grant_type'    => 'client_credentials',
			),
		));

		$data = json_decode((string) $rep->getBody(), true);
		if (empty($data['access_token']))
		{
			throw new \RuntimeException('Graph : jeton non reçu');
		}
		$this->token    = $data['access_token'];
		$this->tokenexp = time() + (int) ($data['expires_in'] ?? 0);
		return $this->token;
	}

	private static function rcpt($list)
	{
		$out = array();
		foreach ((array) $list as $adr)
		{
			$adr = trim((string) $adr);
			if ($adr !== '')
			{
				$out[] = array('emailAddress' => array('address' => $adr));
			}
		}
		return $out;
	}

	/**
	 * @param string|array $to
	 * @param array $attachments [['name' => 'x.pdf', 'content' => données binaires, 'type' => 'application/pdf'], ...]
	 *                           (total < 3 Mo : limite de sendMail en un seul appel)
	 * @param string|null $from boîte expéditrice pour ce mail (sinon celle du constructeur)
	 * @return true si Microsoft a accepté le message (HTTP 202) ; exception sinon
	 */
	public function send($to, $subject, $html, $attachments = array(), $cc = array(), $replyto = null, $save = false, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		if ($from === '')
		{
			throw new \InvalidArgumentException('graphmail : aucune boîte expéditrice');
		}

		$dest = self::rcpt($to);
		if (!$dest)
		{
			throw new \InvalidArgumentException('graphmail : aucun destinataire');
		}

		$msg = array(
			'subject'      => (string) $subject,
			'body'         => array('contentType' => 'HTML', 'content' => (string) $html),
			'toRecipients' => $dest,
		);
		if ($cc)
		{
			$msg['ccRecipients'] = self::rcpt($cc);
		}
		if ($replyto)
		{
			$msg['replyTo'] = self::rcpt($replyto);
		}
		foreach ((array) $attachments as $f)
		{
			$a = array(
				'@odata.type'  => '#microsoft.graph.fileAttachment',
				'name'         => (string) $f['name'],
				'contentType'  => $f['type'] ?? 'application/octet-stream',
				'contentBytes' => base64_encode((string) $f['content']),
			);
			if (!empty($f['cid']))
			{
				$a['isInline']  = true;
				$a['contentId'] = (string) $f['cid'];
			}
			$msg['attachments'][] = $a;
		}

		$this->http(30)->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail', array(
			'headers' => array('Authorization' => 'Bearer '.$this->token()),
			'json'    => array('message' => $msg, 'saveToSentItems' => (bool) $save),
		));
		return true;
	}

	/**
	 * Liste les messages d'un dossier (corps en texte brut).
	 * @param string $folder dossier connu (inbox, deleteditems, ...) ou ID de dossier
	 * @param string|null $filter filtre OData, ex. "isRead eq false"
	 * @return array messages Graph (id, subject, from, receivedDateTime, body['content'], ...)
	 */
	public function messages($folder = 'inbox', $filter = null, $top = 50, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$q = array(
			'$top'     => (int) $top,
			'$select'  => 'id,subject,from,receivedDateTime,body,isRead',
		);
		if ($filter)
		{
			$q['$filter'] = $filter;
		}

		$rep = $this->http(30)->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/mailFolders/'.rawurlencode($folder).'/messages', array(
			'headers' => array(
				'Authorization' => 'Bearer '.$this->token(),
				'Prefer'        => 'outlook.body-content-type="text"',
			),
			'query'   => $q,
		));

		$data = json_decode((string) $rep->getBody(), true);
		return $data['value'] ?? array();
	}

	/**
	 * Comme messages(), mais suit la pagination Graph (@odata.nextLink) jusqu'à $max messages.
	 * $select : champs à récupérer (par défaut : avec le corps, toRecipients et conversationId)
	 */
	public function messagesAll($folder = 'inbox', $filter = null, $max = 2000, $from = null, $select = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$q = array(
			'$top'    => 250,
			'$select' => $select ?: 'id,subject,from,toRecipients,conversationId,receivedDateTime,sentDateTime,body,isRead',
		);
		if ($filter)
		{
			$q['$filter'] = $filter;
		}

		$out = array();
		$url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/mailFolders/'.rawurlencode($folder).'/messages';
		$opt = array(
			'headers' => array(
				'Authorization' => 'Bearer '.$this->token(),
				'Prefer'        => 'outlook.body-content-type="text", IdType="ImmutableId"',
			),
			'query'   => $q,
		);

		while ($url && sizeof($out) < $max)
		{
			$rep  = $this->http(30)->get($url, $opt);
			$data = json_decode((string) $rep->getBody(), true);
			$out  = array_merge($out, $data['value'] ?? array());

			// Le lien suivant contient déjà tous les paramètres
			$url = $data['@odata.nextLink'] ?? null;
			unset($opt['query']);
		}

		return array_slice($out, 0, $max);
	}

	// Marque un message comme lu (ou non lu avec $read = false)
	public function markread($id, $read = true, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$this->http(15)->patch('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/messages/'.rawurlencode($id), array(
			'headers' => array('Authorization' => 'Bearer '.$this->token(), 'Prefer' => 'IdType="ImmutableId"'),
			'json'    => array('isRead' => (bool) $read),
		));
		return true;
	}

	// Déplace un message (dossier connu comme 'deleteditems' ou ID de dossier)
	public function move($id, $folder, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$this->http(15)->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/messages/'.rawurlencode($id).'/move', array(
			'headers' => array('Authorization' => 'Bearer '.$this->token(), 'Prefer' => 'IdType="ImmutableId"'),
			'json'    => array('destinationId' => $folder),
		));
		return true;
	}

	/**
	 * Texte (brut) de plusieurs messages, par paquets de 20 via $batch.
	 * @param array $ids identifiants de messages (immuables de préférence)
	 * @return array id => texte du message (les messages introuvables sont absents)
	 */
	public function bodies($ids, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$out = array();

		foreach (array_chunk(array_values(array_unique($ids)), 20) as $chunk)
		{
			$req = array();
			foreach ($chunk as $k => $id)
			{
				$req[] = array(
					'id'      => (string) $k,
					'method'  => 'GET',
					'url'     => '/users/'.rawurlencode($from).'/messages/'.rawurlencode($id).'?$select=body',
					'headers' => array('Prefer' => 'outlook.body-content-type="text", IdType="ImmutableId"'),
				);
			}

			$rep  = $this->http(60)->post('https://graph.microsoft.com/v1.0/$batch', array(
				'headers' => array('Authorization' => 'Bearer '.$this->token()),
				'json'    => array('requests' => $req),
			));
			$data = json_decode((string) $rep->getBody(), true);

			foreach (($data['responses'] ?? array()) as $r)
			{
				if (($r['status'] ?? 0) == 200 && isset($chunk[(int) $r['id']]))
				{
					$out[$chunk[(int) $r['id']]] = $r['body']['body']['content'] ?? '';
				}
			}
		}

		return $out;
	}

	/**
	 * Plusieurs requêtes GET en un appel ($batch, paquets de 20).
	 * @param array $urls clé => URL relative à https://graph.microsoft.com/v1.0 (ex. "/users/x/messages?$select=id")
	 * @return array clé => réponse décodée (seules les réponses HTTP 200 sont renvoyées)
	 */
	public function batchGet($urls)
	{
		$out = array();
		foreach (array_chunk($urls, 20, true) as $chunk)
		{
			$req = array();
			$map = array();
			foreach ($chunk as $key => $url)
			{
				$n = (string) sizeof($req);
				$map[$n] = $key;
				$req[] = array(
					'id'      => $n,
					'method'  => 'GET',
					'url'     => $url,
					'headers' => array('Prefer' => 'IdType="ImmutableId"'),
				);
			}

			$rep  = $this->http(60)->post('https://graph.microsoft.com/v1.0/$batch', array(
				'headers' => array('Authorization' => 'Bearer '.$this->token()),
				'json'    => array('requests' => $req),
			));
			$data = json_decode((string) $rep->getBody(), true);

			foreach (($data['responses'] ?? array()) as $r)
			{
				if (($r['status'] ?? 0) == 200 && isset($map[$r['id']]))
				{
					$out[$map[$r['id']]] = $r['body'] ?? array();
				}
			}
		}
		return $out;
	}

	// Boîte utilisée par défaut (pour construire des URL de batchGet)
	public function mailbox()
	{
		return $this->from;
	}
}
