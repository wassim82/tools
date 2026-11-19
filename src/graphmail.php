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
 * $atts = $gm->attachments($id);  $f = $gm->attachment($id, $atts[0]['id']);   // pièces jointes
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
	 * Texte (brut) de plusieurs messages, via $batch (paquets de 20 en parallèle).
	 * @param array $ids identifiants de messages (immuables de préférence)
	 * @return array id => texte du message (les messages introuvables sont absents)
	 */
	public function bodies($ids, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$urls = array();
		foreach (array_unique($ids) as $id)
		{
			$urls[$id] = '/users/'.rawurlencode($from).'/messages/'.rawurlencode($id).'?$select=body';
		}

		$out = array();
		foreach ($this->batchGet($urls, 'outlook.body-content-type="text", IdType="ImmutableId"') as $id => $b)
		{
			$out[$id] = $b['body']['content'] ?? '';
		}
		return $out;
	}

	/**
	 * Plusieurs requêtes GET via $batch (paquets de 20, envoyés en parallèle).
	 * @param array $urls clé => URL relative à https://graph.microsoft.com/v1.0 (ex. "/users/x/messages?$select=id")
	 * @return array clé => réponse décodée (seules les réponses HTTP 200 sont renvoyées)
	 */
	public function batchGet($urls, $prefer = 'IdType="ImmutableId"')
	{
		$out = array();
		$client = $this->http(60);
		$token = $this->token();
		$promises = array();
		$maps = array();

		// Un appel $batch par paquet de 20, tous envoyés en même temps
		foreach (array_chunk($urls, 20, true) as $c => $chunk)
		{
			$req = array();
			foreach ($chunk as $key => $url)
			{
				$n = (string) sizeof($req);
				$maps[$c][$n] = $key;
				$req[] = array(
					'id'      => $n,
					'method'  => 'GET',
					'url'     => $url,
					'headers' => array('Prefer' => $prefer),
				);
			}

			$promises[$c] = $client->postAsync('https://graph.microsoft.com/v1.0/$batch', array(
				'headers' => array('Authorization' => 'Bearer '.$token),
				'json'    => array('requests' => $req),
			));
		}

		// Attend toutes les réponses ; un paquet en échec est ignoré, les autres sont gardés
		foreach (\GuzzleHttp\Promise\Utils::settle($promises)->wait() as $c => $res)
		{
			if ($res['state'] !== 'fulfilled')
			{
				continue;
			}
			$data = json_decode((string) $res['value']->getBody(), true);
			foreach (($data['responses'] ?? array()) as $r)
			{
				if (($r['status'] ?? 0) == 200 && isset($maps[$c][$r['id']]))
				{
					$out[$maps[$c][$r['id']]] = $r['body'] ?? array();
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

	/**
	 * Liste des pièces jointes d'un message (sans leur contenu).
	 * @return array [['id' => ..., 'name' => ..., 'contentType' => ..., 'size' => ..., 'isInline' => bool, 'kind' => file|item|reference], ...]
	 */
	public function attachments($id, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$rep = $this->http(30)->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/messages/'.rawurlencode($id).'/attachments', array(
			'headers' => array('Authorization' => 'Bearer '.$this->token(), 'Prefer' => 'IdType="ImmutableId"'),
			'query'   => array('$select' => 'id,name,contentType,size,isInline'),
		));
		$data = json_decode((string) $rep->getBody(), true);

		// Sorte de pièce jointe : file, item (e-mail joint) ou reference (lien en ligne)
		$out = array();
		foreach (($data['value'] ?? array()) as $a)
		{
			$t = $a['@odata.type'] ?? '';
			$a['kind'] = ($t == '#microsoft.graph.itemAttachment') ? 'item' : (($t == '#microsoft.graph.referenceAttachment') ? 'reference' : 'file');
			if ($a['kind'] == 'item' && !preg_match('/\.eml$/i', $a['name'] ?? ''))
			{
				$a['name'] = ($a['name'] ?? 'message').'.eml';
			}
			$out[] = $a;
		}
		return $out;
	}

	/**
	 * Une pièce jointe avec son contenu (fichier brut, ou e-mail joint au format .eml).
	 * @return array ['name' => ..., 'type' => ..., 'content' => données binaires]
	 *         content vide si la pièce jointe n'a pas de contenu téléchargeable (lien OneDrive, etc.)
	 */
	public function attachment($id, $aid, $from = null)
	{
		$from = trim((string) ($from ?: $this->from));
		$url  = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/messages/'.rawurlencode($id).'/attachments/'.rawurlencode($aid);
		$hdr  = array('Authorization' => 'Bearer '.$this->token(), 'Prefer' => 'IdType="ImmutableId"');

		// Métadonnées : nom, type de contenu, sorte de pièce jointe
		$meta = json_decode((string) $this->http(30)->get($url, array(
			'headers' => $hdr,
			'query'   => array('$select' => 'name,contentType'),
		))->getBody(), true);

		$kind = $meta['@odata.type'] ?? '';
		$name = $meta['name'] ?? 'attachment';
		$type = $meta['contentType'] ?? 'application/octet-stream';

		// Lien vers un fichier en ligne : rien à télécharger
		if ($kind == '#microsoft.graph.referenceAttachment')
		{
			return array('name' => $name, 'type' => $type, 'content' => '');
		}

		// E-mail joint : contenu MIME complet, à ouvrir dans Outlook
		if ($kind == '#microsoft.graph.itemAttachment')
		{
			$type = 'message/rfc822';
			if (!preg_match('/\.eml$/i', $name))
			{
				$name .= '.eml';
			}
		}

		// Contenu brut : fichier tel quel, ou e-mail au format MIME
		$content = (string) $this->http(60)->get($url.'/$value', array('headers' => $hdr))->getBody();

		return array('name' => $name, 'type' => $type, 'content' => $content);
	}
}
