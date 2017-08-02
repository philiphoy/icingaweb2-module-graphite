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
    protected $serviceMacro = 'icinga2.$host.name$.services.$service.name$.$service.check_command$.perfdata.$metric$.value';
    protected $hostMacro = 'icinga2.$host.name$.host.$host.check_command$.perfdata.$metric$.value';
    protected $imageUrlMacro = '&target=$target$&source=0&width=300&height=120&hideAxes=true&lineWidth=2&hideLegend=true&colorList=$colorList$&areaMode=$areaMode$&areaAlpha=$areaAlpha$';
    protected $largeImageUrlMacro = '&target=$target$&source=0&width=800&height=700&colorList=$colorList$&lineMode=connected&areaMode=$areaMode$&areaAlpha=$areaAlpha$';
    protected $DerivativeMacro = 'summarize(nonNegativeDerivative($target$),%27$summarizeInterval$%27,%27$summarizeFunc$%27)';
    protected $legacyMode = false;
    protected $graphiteKeys = array();
    protected $graphiteLabels = array();
    protected $areaMode = "all";
    protected $graphType = "normal";
    protected $summarizeInterval = "10min";
    protected $summarizeFunc = "sum";
    protected $areaAlpha = "0.1";
    protected $colorList = "049BAF,EE1D00,04B06E,0446B0,871E10,CB315D,B06904,B0049C";
    protected $iframeWidth = "800px";
    protected $iframeHeight = "700px";
    protected $remoteFetch = false;
    protected $remoteVerifyPeer = true;
    protected $remoteVerifyPeerName = true;

    protected function init()
    {
        $cfg = Config::module('graphite')->getSection('graphite');
        $this->baseUrl = rtrim($cfg->get('base_url', $this->baseUrl), '/');
        $this->legacyMode = filter_var($cfg->get('legacy_mode', $this->legacyMode), FILTER_VALIDATE_BOOLEAN);
        $this->serviceMacro = $cfg->get('service_name_template', $this->serviceMacro);
        $this->hostMacro = $cfg->get('host_name_template', $this->hostMacro);
        $this->imageUrlMacro = $cfg->get('graphite_args_template', $this->imageUrlMacro);
        $this->largeImageUrlMacro = $cfg->get('graphite_large_args_template', $this->largeImageUrlMacro);
        $this->iframeWidth = $cfg->get('graphite_iframe_w', $this->iframeWidth);
        $this->iframeHeight = $cfg->get('graphite_iframe_h', $this->iframeHeight);
        $this->areaMode = $cfg->get('graphite_area_mode', $this->areaMode);
        $this->areaAlpha = $cfg->get('graphite_area_alpha', $this->areaAlpha);
        $this->summarizeInterval = $cfg->get('graphite_summarize_interval', $this->summarizeInterval);
        $this->colorList = $cfg->get('graphite_color_list', $this->colorList);

        $this->remoteFetch = filter_var($cfg->get('remote_fetch', $this->remoteFetch), FILTER_VALIDATE_BOOLEAN);
        $this->remoteVerifyPeer = filter_var($cfg->get('remote_verify_peer', $this->remoteVerifyPeer), FILTER_VALIDATE_BOOLEAN);
        $this->remoteVerifyPeerName = filter_var($cfg->get('remote_verify_peer_name', $this->remoteVerifyPeerName), FILTER_VALIDATE_BOOLEAN);
    }

    private function parseGrapherConfig($graphite_vars)
    {
        if (!empty($graphite_vars)) {
            if (!empty($graphite_vars->area_mode)) {
                $this->areaMode = $graphite_vars->area_mode;
            }
            if (!empty($graphite_vars->area_alpha)) {
                $this->areaAlpha = $graphite_vars->area_alpha;
            }
            if (!empty($graphite_vars->graph_type)) {
                $this->graphType = $graphite_vars->graph_type;
            }
            if (!empty($graphite_vars->summarize_interval)) {
                $this->summarizeInterval = $graphite_vars->summarize_interval;
            }
            if (!empty($graphite_vars->summarize_func)) {
                $this->summarizeFunc = $graphite_vars->summarize_func;
            }
            if (!empty($graphite_vars->color_list)) {
                $this->colorList = $graphite_vars->color_list;
            }
        }
    }

    private function getKeysAndLabels($vars)
    {
        if (array_key_exists("graphite_keys", $vars)) {
            $this->graphiteKeys = $vars["graphite_keys"];
            $this->graphiteLabels = $vars["graphite_keys"];
            if (array_key_exists("graphite_labels", $vars)) {
                if (count($vars["graphite_keys"]) == count($vars["graphite_labels"])) {
                    $this->graphiteLabels = $vars["graphite_labels"];
                }
            }
        }
    }

    private function getPerfdataKeys($object)
    {
        foreach (PerfdataSet::fromString($object->perfdata)->asArray() as $pd) {
            $this->graphiteKeys[] = $pd->getLabel();
            $this->graphiteLabels[] = $pd->getLabel();
        }
    }

    private function getPreviewImage($host, $service, $metric)
    {
        if ($host != null){
            $target = Macro::resolveMacros($this->hostMacro, $host, $this->legacyMode, true);
        } elseif  ($service != null ){
            $target = Macro::resolveMacros($this->serviceMacro, $service, $this->legacyMode, true);
        } else {
           $target = '';
        }

        if ($this->graphType == "derivative"){
            $target = Macro::resolveMacros($this->DerivativeMacro, array(
                "target" => $target,
                "summarizeInterval" => $this->summarizeInterval,
                "summarizeFunc" => $this->summarizeFunc
            ), $this->legacyMode, false, false);
        }
        $target = Macro::resolveMacros($target, array("metric"=>$metric), $this->legacyMode, true, true);
        $imgUrl = $this->getImgUrl($target);

        if ($this->remoteFetch) {
            $imgUrl = $this->inlineImage($imgUrl);
            $url = Url::fromPath('graphite', array(
                    'graphite_url' => urlencode($target),
                    'graphite_iframe_w' => urlencode($this->iframeWidth),
                    'graphite_iframe_h' => urlencode($this->iframeHeight)
            ));
        } else {
            $url = Url::fromPath('graphite', array(
                    'graphite_url' => urlencode($this->getLargeImgUrl($target)),
                    'graphite_iframe_w' => urlencode($this->iframeWidth),
                    'graphite_iframe_h' => urlencode($this->iframeHeight)
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

    public function has(MonitoredObject $object)
    {
        if (($object instanceof Host)||($object instanceof Service)) {
            return true;
        } else {
            return false;
        }
    }

    public function getPreviewHtml(MonitoredObject $object)
    {
        $object->fetchCustomvars();

        if (array_key_exists("graphite", $object->customvars)) {
            $this->parseGrapherConfig($object->customvars["graphite"]);
        }

        $this->getKeysAndLabels($object->customvars);
        if (empty($this->graphiteKeys)) {
          $this->getPerfDataKeys($object);
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

        for ($key = 0; $key < count($this->graphiteKeys); $key++) {
            $html .= "<tr><th>\n"
                  . $this->graphiteLabels[$key]
                  . '</th><td>'
                  . $this->getPreviewImage($host, $service, $this->graphiteKeys[$key])
                  . "</td>\n"
                  . "<tr>\n";
        }

        $html .= "</tbody></table>\n";
        return $html;
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
    return $this->baseUrl . Macro::resolveMacros(
        $this->imageUrlMacro, array(
                "target" => $target,
                "areaMode" => $this->areaMode,
                "areaAlpha" => $this->areaAlpha,
                "colorList" => $this->colorList
        ), $this->legacyMode, false);
    }

    public function getLargeImgUrl($target, $from=false) {
        $url = $this->baseUrl . Macro::resolveMacros(
            $this->largeImageUrlMacro, array(
                "target" => $target,
                "areaMode" => $this->areaMode,
                "areaAlpha" => $this->areaAlpha,
                "colorList" => $this->colorList
            ), $this->legacyMode,  false);
        if ($from !== false) {
            $url = $url.'&from='.$from;
        }

        return $url;
    }
}
