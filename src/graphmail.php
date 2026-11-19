<?php
/**
 * Boîte d'envoi du registre (noreply@) : avis de non-remise et messages reçus.
 * Dossiers : boîte de réception, éléments envoyés, éléments supprimés (2 dernières années).
 * Réservé aux administrateurs (TYPE 4). Actions (lu / non lu / supprimer / restaurer) via bounces_data.php.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rp = "";
session_start();
$page = "bounces";

if(!isset($_SESSION['login']) || $_SESSION['login'] != 'ok' || $_SESSION['user']['TYPE'] != 4)
{
	header("Location: index");
	exit;
}

require_once $rp . '_mdcfg.php';
require_once $rp . '_php/bounce_lib.php';
require_once $rp . '_app/vendor/autoload.php';
$wasql = new \madtec\db\mysqli($mdtccfg['mysql']['host'], $mdtccfg['mysql']['user'], $mdtccfg['mysql']['pass'], $mdtccfg['mysql']['bdd']);

$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES); };

// Dossier
$folders = array('inbox' => 'Inbox', 'sentitems' => 'Sent items', 'deleteditems' => 'Deleted items');
$folder  = (isset($_GET['f']) && isset($folders[$_GET['f']])) ? $_GET['f'] : 'inbox';

// Type affiché (pas pour les éléments envoyés)
$types = array('all' => 'All', 'ndr' => 'Undelivered e-mails', 'msg' => 'Received messages');
$type  = (isset($_GET['t']) && isset($types[$_GET['t']])) ? $_GET['t'] : 'all';
if($folder == 'sentitems')
{
	$type = 'all';
}

// Pagination
$perpage = 20;
$pnum = max(1, (int)($_GET['p'] ?? 1));

// Uniquement les e-mails des 2 dernières années
$since = "receivedDateTime ge ".gmdate("Y-m-d\\TH:i:s\\Z", strtotime("-2 years"));

// Couleurs par type
$colors = array('unknown' => '#e30613', 'domain' => '#e30613', 'full' => '#e08a00', 'blocked' => '#e08a00', 'forward' => '#1d71b8', 'other' => '#8a96a3',
                'replied' => '#2e8b57', 'noreply' => '#e08a00', 'sent' => '#1d71b8');

// Première adresse e-mail d'un texte
$first_adr = function($s) {
	return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string)$s, $mm) ? strtolower($mm[0]) : '';
};

$err  = '';
$rows = array();
try
{
	$gm = new \madtec\tools\graphmail($mdtccfg['msgraph']['tenantId'], $mdtccfg['msgraph']['clientId'], $mdtccfg['msgraph']['clientSecret'], $mdtccfg['msgraph']['email']);
	$msgs = $gm->messagesAll($folder, $since, 2000);

	// Index des réponses envoyées (pour savoir si un message reçu a eu une réponse)
	$sent_conv = array();   // conversationId => date du dernier envoi
	$sent_re   = array();   // adresse => dates des "RE:" envoyés
	if($folder != 'sentitems')
	{
		$sent = $gm->messagesAll('sentitems', $since, 2000, null, 'id,subject,toRecipients,conversationId,sentDateTime');
		foreach($sent as $s)
		{
			$d = $s['sentDateTime'] ?? '';
			$c = $s['conversationId'] ?? '';
			if($c != '' && (!isset($sent_conv[$c]) || $d > $sent_conv[$c]))
			{
				$sent_conv[$c] = $d;
			}
			if(preg_match('/^\s*(re|tr|fw|fwd)\s*:/i', $s['subject'] ?? ''))
			{
				foreach(($s['toRecipients'] ?? array()) as $tr)
				{
					$sent_re[strtolower($tr['emailAddress']['address'] ?? '')][] = $d;
				}
			}
		}
	}

	foreach($msgs as $m)
	{
		$txt  = $m['body']['content'] ?? '';
		$date = $m['receivedDateTime'] ?? ($m['sentDateTime'] ?? '');
		$row  = array(
			'id'   => $m['id'],
			'read' => !empty($m['isRead']),
			'date' => $date,
			'subj' => $m['subject'] ?? '',
		);

		// Éléments envoyés
		if($folder == 'sentitems')
		{
			$to = array();
			foreach(($m['toRecipients'] ?? array()) as $tr)
			{
				$to[] = strtolower($tr['emailAddress']['address'] ?? '');
			}
			$row += array('kind' => 'sent', 'label' => 'Sent', 'code' => '', 'color' => 'sent', 'rcpt' => $to, 'body' => trim($txt), 'line' => '', 'hdr' => '', 'note' => '');
		}
		// Avis de non-remise
		else if(is_ndr($m))
		{
			if($type == 'msg')
			{
				continue;
			}
			list($diag, $hdr) = ndr_split($txt);
			$why  = ndr_reason($diag);
			$orig = ndr_origin($hdr);

			if($why['type'] == 'forward')
			{
				$a = $first_adr($orig['from']);
				$rcpt = ($a != '') ? array($a) : array();
			}
			else
			{
				$rcpt = ndr_rcpt($txt, $bounce_ignore_dom, $bounce_ignore_usr);
			}
			if($orig['subject'] != '')
			{
				$row['subj'] = $orig['subject'];
			}
			$row += array('kind' => 'ndr', 'label' => $why['label'], 'code' => $why['code'], 'color' => $why['type'], 'rcpt' => $rcpt,
			              'body' => trim(preg_replace("/\n\s*\n+/", "\n", $diag)), 'line' => $why['line'], 'hdr' => trim($hdr),
			              'note' => ($why['type'] == 'forward' ? 'Reply by e-mail from :' : ''));
		}
		// Message reçu d'une personne
		else
		{
			if($type == 'ndr')
			{
				continue;
			}
			$from = strtolower($m['from']['emailAddress']['address'] ?? '');
			$name = $m['from']['emailAddress']['name'] ?? '';

			// Réponse : même conversation, ou "RE:" envoyé à cette adresse après réception
			$rep = '';
			$c = $m['conversationId'] ?? '';
			if($c != '' && isset($sent_conv[$c]) && $sent_conv[$c] > $date)
			{
				$rep = $sent_conv[$c];
			}
			foreach(($sent_re[$from] ?? array()) as $d)
			{
				if($d > $date && ($rep == '' || $d < $rep))
				{
					$rep = $d;
				}
			}

			$row += array('kind' => 'msg', 'label' => ($rep != '' ? 'Replied' : 'No reply'), 'code' => ($rep != '' ? date("d/m/Y", strtotime($rep)) : ''),
			              'color' => ($rep != '' ? 'replied' : 'noreply'), 'rcpt' => ($from != '' ? array($from) : array()),
			              'body' => trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $txt)), 'line' => '', 'hdr' => '',
			              'note' => ($name != '' && strtolower($name) != $from ? $name : ''));
		}

		$rows[] = $row;
	}
	usort($rows, function($a, $b) { return strcmp($b['date'], $a['date']); });
}
catch(\Exception $e)
{
	error_log("bounces: lecture de la boîte impossible - ".$e->getMessage());
	$err = "Unable to read the mailbox. See the server log for details.";
}

// Résumé par type
$sum = array();
foreach($rows as $r)
{
	$k = ($r['kind'] == 'msg' ? 'Message - ' : '').$r['label'];
	if(!isset($sum[$k]))
	{
		$sum[$k] = array('n' => 0, 'color' => $r['color']);
	}
	$sum[$k]['n']++;
}
arsort($sum);

// Page courante
$total = sizeof($rows);
$pages = max(1, (int)ceil($total / $perpage));
$pnum  = min($pnum, $pages);
$prows = array_slice($rows, ($pnum - 1) * $perpage, $perpage);

// Notifications liées (uniquement pour la page affichée)
foreach($prows as $k => $r)
{
	$feeds = array();
	foreach($r['rcpt'] as $a)
	{
		foreach(ndr_feeds($wasql, $a) as $f)
		{
			$feeds[$f['ID']] = $f;
		}
	}
	$prows[$k]['feeds'] = $feeds;
}

// URL avec paramètres
$url = function($p) use ($folder, $type) {
	return "bounces?f=".$folder."&t=".$type."&p=".(int)$p;
};

$includef = array();
include_once("_headers.php");
?>
<style>
	/* La hauteur du conteneur suit le contenu (style.css le fixe à 100% de la fenêtre) */
	#maincont { height: auto !important; min-height: 100%; padding-bottom: 30px; }
	.ndr-table { table-layout: fixed; width: 100%; background-color: #ffffff; font-size: 14px; }
	.ndr-table th { background-color: #1d71b8; color: #ffffff; font-weight: bold; border: 0; }
	.ndr-table td { vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
	.ndr-table tr.ndr-main > td { border-top: 1px solid #e3eaf1; padding-top: 10px; padding-bottom: 10px; }
	.ndr-table tr.ndr-main:hover > td { background-color: #f8fafc; }
	.ndr-table tr.ndr-unread > td:first-child { border-left: 3px solid #1d71b8; }
	.ndr-table tr.ndr-unread .ndr-subj { font-weight: bold; }
	.ndr-code { font-size: 12px; color: #8a96a3; }
	.ndr-muted { color: #8a96a3; }
	.ndr-subj { font-size: 13px; color: #333333; }
	.ndr-detail td { border-top: 0 !important; padding: 0 12px 14px 12px !important; }
	.ndr-box { background-color: #f5f8fb; border-left: 3px solid #1d71b8; padding: 12px 14px; font-size: 12px; line-height: 18px; color: #333333; }
	.ndr-box pre { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; font-family: inherit; font-size: 12px; color: #555555; margin: 0; max-height: 350px; overflow: auto; background: #ffffff; border: 1px solid #e3eaf1; padding: 8px; }
	.ndr-box pre.ndr-tech { font-family: monospace; font-size: 11px; word-break: break-all; }
	.ndr-badge { display: inline-block; padding: 4px 10px; margin: 0 6px 6px 0; border-radius: 12px; font-size: 13px; background-color: #ffffff; border: 1px solid #d6e2ee; }
	.ndr-toggle { font-size: 12px; color: #1d71b8; cursor: pointer; }
	.ndr-act { white-space: nowrap; text-align: right; }
	.ndr-act .btn { padding: 2px 8px; font-size: 13px; margin-left: 4px; }
	.ndr-pag .page-link { color: #1d71b8; font-size: 14px; }
	.ndr-pag .page-item.active .page-link { background-color: #1d71b8; border-color: #1d71b8; color: #ffffff; }
</style>
<?php

print "<body class='bodyw' style='height:100%;'>";

	print "<div class='container maincontainer' id='maincont'>";

		print "<div class='row' style=\"background-image: url('banner.jpg');border-bottom:4px solid #1d71b8;\">";
			print "<div class='col-sm-12' style='height:250px;'></div>";
		print "</div>";

		print "<div class='row' style='padding:0 20px 20px 20px;'>";
			print "<div class='col-12'>";
				include("_menu.php");
			print "</div>";
		print "</div>";

		// Titre + filtres
		print "<div class='row' style='padding:0 30px 10px 30px;'>";
			print "<div class='col-6'>";
				print "<p class='tbld t24 tcbl' style='margin:0;'>Register mailbox</p>";
				print "<p class='t14 ndr-muted' style='margin:0;'>".$h($mdtccfg['msgraph']['email'])." - last 2 years</p>";
			print "</div>";
			print "<div class='col-6 text-right'>";
				print "<form method='get' style='margin-top:10px;'>";
					if($folder != 'sentitems')
					{
						print "<select name='t' class='form-control t14' style='display:inline-block;width:auto;margin-right:6px;' onchange='this.form.submit()'>";
						foreach($types as $k => $lab)
						{
							print "<option value='".$k."'".($k == $type ? " selected" : "").">".$h($lab)."</option>";
						}
						print "</select>";
					}
					print "<select name='f' class='form-control t14' style='display:inline-block;width:auto;' onchange='this.form.submit()'>";
					foreach($folders as $k => $lab)
					{
						print "<option value='".$k."'".($k == $folder ? " selected" : "").">".$h($lab)."</option>";
					}
					print "</select>";
				print "</form>";
			print "</div>";
		print "</div>";

		print "<div class='row' style='padding:0 30px 30px 30px;'>";
			print "<div class='col-12'>";

			if($err != '')
			{
				print "<div class='alert alert-danger'>".$h($err)."</div>";
			}
			else if($total == 0)
			{
				print "<div class='alert alert-success'>No e-mail in this folder.</div>";
			}
			else
			{
				// Résumé
				print "<p class='t14 tcbl' style='margin:0 0 8px 0;'><b id='ndr-count'>".$total."</b> e-mail(s) found :</p>";
				if($folder != 'sentitems')
				{
					print "<div style='margin-bottom:14px;'>";
					foreach($sum as $lab => $s)
					{
						print "<span class='ndr-badge'><b style='color:".$colors[$s['color']].";'>".$s['n']."</b> ".$h($lab)."</span>";
					}
					print "</div>";
				}

				print "<table class='table table-sm ndr-table'>";
				print "<colgroup><col style='width:95px;'><col style='width:26%;'><col style='width:24%;'><col><col style='width:90px;'></colgroup>";
				print "<thead><tr><th>Date</th><th>".($folder == 'sentitems' ? "Subject" : "Status / subject")."</th><th>".($folder == 'sentitems' ? "To" : "Address")."</th><th>Notifications</th><th></th></tr></thead><tbody>";

				foreach($prows as $k => $r)
				{
					print "<tr class='ndr-main".($r['read'] ? "" : " ndr-unread")."' id='ndrr".$k."' data-id='".$h($r['id'])."'>";
						// Date
						print "<td>".$h(date("d/m/Y", strtotime($r['date'])))."<br/><span class='ndr-code'>".$h(date("H:i", strtotime($r['date'])))."</span></td>";

						// Statut + sujet
						print "<td>";
						if($r['kind'] != 'sent')
						{
							print "<b style='color:".$colors[$r['color']].";'>".($r['kind'] == 'msg' ? "Message - " : "").$h($r['label'])."</b>";
							if($r['code'] != '')
							{
								print " <span class='ndr-code'>".$h($r['code'])."</span>";
							}
							print "<br/>";
						}
						print "<span class='ndr-subj'>".$h($r['subj'] != '' ? $r['subj'] : '(no subject)')."</span>";
						print "<br/><a class='ndr-toggle' data-toggle='collapse' href='#ndr".$k."'>Details</a>";
						print "</td>";

						// Adresse(s)
						print "<td>";
						if($r['note'] != '')
						{
							print "<span class='ndr-code'>".$h($r['note'])."</span><br/>";
						}
						if(sizeof($r['rcpt']) == 0)
						{
							print "<span class='ndr-muted'>Not identified</span>";
						}
						foreach($r['rcpt'] as $a)
						{
							print "<b>".$h($a)."</b><br/>";
						}
						print "</td>";

						// Notifications
						print "<td>";
						if(sizeof($r['feeds']) == 0)
						{
							print "<span class='ndr-muted'>-</span>";
						}
						foreach($r['feeds'] as $f)
						{
							print "<a href='register?pid=".(int)$f['ID']."' target='_blank' style='color:#1d71b8;'>".sprintf("%06d", $f['ID'])." - ".$h($f['NAME'])."</a>";
							print " <span class='ndr-muted'>(".$h($f['CONTACT_NAME']." ".$f['CONTACT_SURNAME']).")</span><br/>";
						}
						print "</td>";

						// Actions
						print "<td class='ndr-act'>";
							if($folder != 'sentitems')
							{
								if($r['read'])
								{
									print "<button type='button' class='btn btn-outline-secondary' title='Mark as unread' onclick='ndract(".$k.",\"unread\")'><i class='fas fa-envelope'></i></button>";
								}
								else
								{
									print "<button type='button' class='btn btn-outline-primary' title='Mark as read' onclick='ndract(".$k.",\"read\")'><i class='fas fa-envelope-open'></i></button>";
								}
							}
							if($folder == 'deleteditems')
							{
								print "<button type='button' class='btn btn-outline-success' title='Restore to inbox' onclick='ndract(".$k.",\"restore\")'><i class='fas fa-undo'></i></button>";
							}
							else
							{
								print "<button type='button' class='btn btn-outline-danger' title='Delete' onclick='ndract(".$k.",\"del\")'><i class='fas fa-trash-alt'></i></button>";
							}
						print "</td>";
					print "</tr>";

					// Détail (replié)
					print "<tr class='collapse ndr-detail' id='ndr".$k."'><td colspan='5'>";
						print "<div class='ndr-box'>";
							if($r['line'] != '')
							{
								print "<p style='margin:0 0 10px 0;'><b>Server response :</b> ".$h($r['line'])."</p>";
							}
							print "<pre>".$h(mb_substr($r['body'], 0, 5000))."</pre>";
							if($r['hdr'] != '')
							{
								print "<p style='margin:10px 0 4px 0;'><a class='ndr-toggle' data-toggle='collapse' href='#ndrh".$k."'>Show technical headers</a></p>";
								print "<div class='collapse' id='ndrh".$k."'><pre class='ndr-tech'>".$h($r['hdr'])."</pre></div>";
							}
						print "</div>";
					print "</td></tr>";
				}

				print "</tbody></table>";

				// Pagination
				if($pages > 1)
				{
					print "<nav class='ndr-pag'><ul class='pagination justify-content-center'>";
						print "<li class='page-item".($pnum == 1 ? " disabled" : "")."'><a class='page-link' href='".$url($pnum - 1)."'>&laquo;</a></li>";
						for($i = 1; $i <= $pages; $i++)
						{
							// Première, dernière et pages autour de la page courante
							if($i == 1 || $i == $pages || abs($i - $pnum) <= 2)
							{
								print "<li class='page-item".($i == $pnum ? " active" : "")."'><a class='page-link' href='".$url($i)."'>".$i."</a></li>";
							}
							else if(abs($i - $pnum) == 3)
							{
								print "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
							}
						}
						print "<li class='page-item".($pnum == $pages ? " disabled" : "")."'><a class='page-link' href='".$url($pnum + 1)."'>&raquo;</a></li>";
					print "</ul></nav>";
				}
			}

			print "</div>";
		print "</div>";

	print "</div>";
?>
<script>
// Action sur un e-mail : read / unread / del / restore
function ndract(k, m)
{
	if(m == 'del' && !confirm("Delete this e-mail ? It will be moved to the deleted items folder."))
	{
		return;
	}

	var $tr = $("#ndrr"+k);
	$("button", $tr).prop("disabled", true);

	$.ajax({
		type: "POST",
		dataType: 'json',
		url: 'bounces_data.php',
		data: { m: m, i: $tr.data("id") },
		success: function(data) {
			if(data.r != 'ok')
			{
				alert(data.msg);
				$("button", $tr).prop("disabled", false);
				return;
			}

			// Supprimé ou restauré : la ligne quitte le dossier
			if(m == 'del' || m == 'restore')
			{
				$tr.add("#ndr"+k).fadeOut(200, function() { $(this).remove(); });
				$("#ndr-count").text(parseInt($("#ndr-count").text()) - 1);
				return;
			}

			// Lu / non lu : on bascule l'état et le bouton
			var read = (m == 'read');
			$tr.toggleClass("ndr-unread", !read);
			var $b = $("button", $tr).first();
			$b.removeClass("btn-outline-primary btn-outline-secondary").addClass(read ? "btn-outline-secondary" : "btn-outline-primary");
			$b.attr("title", read ? "Mark as unread" : "Mark as read");
			$b.attr("onclick", "ndract("+k+",'"+(read ? "unread" : "read")+"')");
			$("i", $b).attr("class", read ? "fas fa-envelope" : "fas fa-envelope-open");
			$("button", $tr).prop("disabled", false);
		},
		error: function() {
			alert("Action failed");
			$("button", $tr).prop("disabled", false);
		}
	});
}
</script>
<?php
include_once("_footer.php");
?>
