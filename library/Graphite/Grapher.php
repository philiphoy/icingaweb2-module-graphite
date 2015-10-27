<?php

namespace Icinga\Module\Graphite;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Plugin\PerfdataSet;
use Icinga\Web\Hook\GrapherHook;
use Icinga\Web\Url;

class Grapher extends GrapherHook
{
    protected $hasPreviews = true;
    protected $hasTinyPreviews = true;
    protected $graphiteConfig;
    protected $baseUrl = 'http://graphite.com/render/?';
    protected $metricPrefix = 'icinga';
    protected $serviceMacro = '$HOSTNAME$.$SERVICEDESC$';
    protected $hostMacro = '$HOSTNAME$';
    protected $imageUrlMacro = '&target=$target$&source=0&width=300&height=120&hideAxes=true&lineWidth=2&hideLegend=true&colorList=049BAF';
    protected $largeImageUrlMacro = '&target=$target$&source=0&width=800&height=700&colorList=049BAF&lineMode=connected';
    protected $legacyMode = 'false';

    protected $remoteFetch = false;
    protected $remoteVerifyPeer = true;
    protected $remoteVerifyPeerName = true;

    protected function init()
    {
        $cfg = Config::module('graphite')->getSection('graphite');
        $this->baseUrl = rtrim($cfg->get('base_url', $this->baseUrl), '/');
        $this->metricPrefix = $cfg->get('metric_prefix', $this->metricPrefix);
        $this->legacyMode = $cfg->get('legacy_mode', $this->legacyMode);
        $this->serviceMacro = $cfg->get('service_name_template', $this->serviceMacro);
        $this->hostMacro = $cfg->get('host_name_template', $this->hostMacro);
        $this->imageUrlMacro = $cfg->get('graphite_args_template', $this->imageUrlMacro);
        $this->largeImageUrlMacro = $cfg->get('graphite_large_args_template', $this->largeImageUrlMacro);

        $this->remoteFetch = $cfg->get('remote_fetch', $this->remoteFetch);
        $this->remoteVerifyPeer = $cfg->get('remote_verify_peer', $this->remoteVerifyPeer);
        $this->remoteVerifyPeerName = $cfg->get('remote_verify_peer_name', $this->remoteVerifyPeerName);
    }

    public function has(MonitoredObject $object)
    {
        if ($object instanceof Host) {
            $service = '_HOST_';
        } elseif ($object instanceof Service) {
            $service = $object->service_description;
        } else {
            return false;
        }

        return true;
    }

    public function getPreviewHtml(MonitoredObject $object)
    {
        $object->fetchCustomvars();
        if (array_key_exists("graphite_keys", $object->customvars))
            $graphiteKeys = $object->customvars["graphite_keys"];
        else {
            $graphiteKeys = array();
            foreach (PerfdataSet::fromString($object->perfdata)->asArray() as $pd)
                $graphiteKeys[] = $pd->getLabel();
        }

        if ($object instanceof Host) {
            $host = $object;
            $service = null;
        } elseif ($object instanceof Service) {
            $service = $object;
            $host = null;
        } else {
            return '';
        }

        $html = "<table class=\"avp newsection\">\n"
               ."<tbody>\n";

        foreach ($graphiteKeys as $metric) {
            $html .= "<tr><th>\n"
                  . "$metric\n" 
                  . '</th><td>'
                  . $this->getPreviewImage($host, $service, $metric)
                  . "</td>\n"
                  . "<tr>\n";
        }

        $html .= "</tbody></table>\n";
        return $html;
    }

    // Currently unused,
    public function getSmallPreviewImage($host, $service = null)
    {       
        return null;
    }

    private function getPreviewImage($host, $service, $metric)
    {        
      
        if ($host != Null){
            $target = Macro::resolveMacros($this->hostMacro, $host, $this->legacyMode);
        } elseif  ($service != null ){
            $target = Macro::resolveMacros($this->serviceMacro, $service, $this->legacyMode);
        } else {
           $target = '';
        }

        $target .= '.'. Macro::escapeMetric($metric, $this->legacyMode);

        if ($this->legacyMode == 'false'){
            $target .= '.value';
        }

        $target = $this->metricPrefix . "." . $target;
	$imgUrl = $this->getImgUrl($target);

	if ($this->remoteFetch) {
	    $imgUrl = $this->inlineImage($imgUrl);
            $url = Url::fromPath('graphite', array(
		'graphite_url' => urlencode($target),
	    ));
	} else {
            $url = Url::fromPath('graphite', array(
                'graphite_url' => urlencode($this->getLargeImgUrl($target))
            ));
	}

        $html = '<a href="%s" title="%s"><img src="%s" alt="%s" width="300" height="120" /></a>';

        return sprintf(
            $html,
            $url,
            $metric,
            $imgUrl,
            $metric
       );
    }

    public function inlineImage($url) {
        $ctx = stream_context_create(array('ssl' => array("verify_peer"=>$this->remoteVerifyPeer, "verify_peer_name"=>$this->remoteVerifyPeerName)));

        $img = @file_get_contents($url, false, $ctx);
        $error = error_get_last();
        if ($error === null) {
            return 'data:image/png;base64,'.base64_encode($img);
        } else {
            throw new \ErrorException($error['message']);
        }
    }

    public function getRemoteFetch() {
        return $this->remoteFetch;
    }

    public function getImgUrl($target) {
	return $this->baseUrl . Macro::resolveMacros($this->imageUrlMacro, array("target" => $target), $this->legacyMode, false);
    }

    public function getLargeImgUrl($target, $from=false) {
       	$url = $this->baseUrl . Macro::resolveMacros($this->largeImageUrlMacro, array("target" => $target), $this->legacyMode,  false);
	if ($from !== false) {
		$url = $url.'&from='.$from;
	}

	return $url;
    }
}
