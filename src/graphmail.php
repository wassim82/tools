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
 * Le nom affiché est TOUJOURS celui de la boîte dans Exchange (Graph ignore un nom fourni) :
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
}
