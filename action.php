<?php
/**
 * DokuWiki Plugin Slack Integration (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Jeremy Ebler <jebler@gmail.com> 2011-09-29
 *
 * DokuWiki log: https://github.com/cosmocode/log.git
 * @author  Adrian Lang <lang@cosmocode.de> 2010-03-28
 *

 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require 'vendor/autoload.php';

class action_plugin_slackhq extends DokuWiki_Action_Plugin {

    function register(Doku_Event_Handler $controller) {
       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
    }

    function handle_action_act_preprocess(Doku_Event $event, $param) {
        global $lang;
        if (isset($event->data['save'])) {
            //if ($event->data['save'] == $lang['btn_save']) {
                $this->handle();
            //}
        }
        return;
    }

    private function handle() {
		global $SUM;
        global $INFO;
		global $conf;

        /* Namespace filter */
        $ns = $this->getConf('slackhq_namespaces');
        if (!empty($ns)) {
            $namespaces = explode(',', $ns);
            $current_namespace = explode(':', $INFO['namespace']);
            if (!in_array($current_namespace[0], $namespaces)) {
                return;
            }
        }

        $fullname = $INFO['userinfo']['name'];
        $username = $INFO['client'];
        $page     = $INFO['namespace'] . $INFO['id'];
        $summary  = $SUM;
        $minor    = (boolean) $_REQUEST['minor'];

 
        $icon = $this->getConf('slackhq_icon');
        $channel = $this->getConf('slackhq_channel');
        $from = $this->getConf('slackhq_name');

        $webhook = $this->getConf('slackhq_webhook');


        $say = '' . $fullname . ' updated the WikiPage <'. $this->urlize() . '|' . $INFO['id'] . '>';
        //if ($minor) $say = $say . ' ['.$this->getLang('slackhq_minor').']';
        //if ($summary) $say = $say . ' / ' . $summary . '';


        $data = "payload=" . json_encode(array(
                "channel"       =>  "#{$channel}",
                "text"          =>  $say,
                "username"      => $from,
                "icon_emoji"    =>  $icon,
                "attachments"  =>  array(array(
                    "fallback" => 'Change summary',
                    "color"    => '#333',
                    "title"    => $INFO['id'],
                    "title_link"=> $this->urlize(),
                    "text"     => $summary,
                    "author"   => $fullname
                    ))
            ));
			
		$ch = curl_init($webhook);
			
		$proxy = $conf['proxy'];
		
		// If DokuWiki is set up with proxy settings then use them in our cURL request
		if (!empty($proxy)) {
			// Configure the proxy address and port
			$proxyAddress = $proxy['host'] . ':' . $proxy['port'];
			
			curl_setopt($ch, CURLOPT_PROXY, $proxyAddress);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			// TODO: This may be required internally but best to add a config flag/path to local certificate file
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			
			// Only include a username and password if we actually have one
			if (!empty($proxy['user']) && !empty($proxy['pass'])) {
				$proxyAuth = $proxy['user'] . ':' . conf_decodeString($proxy['port']);
				
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
			}
			
		}
    
        // You can get your webhook endpoint from your Slack settings
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
		
		// This should ideally display or log only for Admin users/in debugging mode
		if ($result === false){
			echo 'cURL error when posting Wiki save notification to Slack: ' . curl_error($ch);
		}
		
        curl_close($ch);
    }

    /* Make our URLs! */
    private function urlize() {

        global $INFO;
        global $conf;
        $page = $INFO['id'];

        switch($conf['userewrite']) {
            case 0:
                $url = DOKU_URL . "doku.php?id=" . $page;
                break;
            case 1:
                if ($conf['useslash']) {
                    $page = str_replace(":", "/", $page);
                }
                $url = DOKU_URL . $page;
                break;
            case 2:
                if ($conf['useslash']) {
                    $page = str_replace(":", "/", $page);
                }
                $url = DOKU_URL . "doku.php/" . $page;
                break;
        }
        return $url;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
