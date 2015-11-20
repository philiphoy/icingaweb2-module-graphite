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
    protected $serviceMacro = '$host.name$.services.$service.name$.$service.check_command$.perfdata';
    protected $hostMacro = '$host.name$.host.$host.check_command$.perfdata';
    protected $imageUrlMacro = '&target=$target$&source=0&width=300&height=120&hideAxes=true&lineWidth=2&hideLegend=true&colorList=049BAF,FFAA44,FFAA99';
    protected $largeImageUrlMacro = '&target=$target$&source=0&width=800&height=700&colorList=049BAF,FFAA44,FFAA99&lineMode=connected';
    protected $thresholdImageUrlMacro = '&target=threshold($warning$,\'warning\',orange)&target=threshold($critical$,\'critical\',red)';
    protected $legacyMode = false;

    protected function init()
    {
        $cfg = Config::module('graphite')->getSection('graphite');
        $this->baseUrl = rtrim($cfg->get('base_url', $this->baseUrl), '/');
        $this->metricPrefix = $cfg->get('metric_prefix', $this->metricPrefix);
        $this->legacyMode = filter_var($cfg->get('legacy_mode', $this->legacyMode), FILTER_VALIDATE_BOOLEAN);
        $this->serviceMacro = $cfg->get('service_name_template', $this->serviceMacro);
        $this->hostMacro = $cfg->get('host_name_template', $this->hostMacro);
        $this->imageUrlMacro = $cfg->get('graphite_args_template', $this->imageUrlMacro);
        $this->largeImageUrlMacro = $cfg->get('graphite_large_args_template', $this->largeImageUrlMacro);
        $this->thresholdImageUrlMacro = $cfg->get('graphite_threshold_args_template', $this->thresholdImageUrlMacro);
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
            $thresholds = self::getMetricThresholds($object, $metric);
            $html .= "<tr><th>\n"
                  . "$metric\n"
                  . '</th><td>'
                  . $this->getPreviewImage($host, $service, $metric, $thresholds)
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

        if ($this->legacyMode == false){
            $target .= '.value';
        }

        $target = $this->metricPrefix . "." . $target;


        $imgUrl = $this->baseUrl . Macro::resolveMacros($this->imageUrlMacro, array("target" => $target), $this->legacyMode, false)
                                 . Macro::resolveMacros($this->thresholdImageUrlMacro, $thresholds, $this->legacyMode,false);

        $largeImgUrl = $this->baseUrl . Macro::resolveMacros($this->largeImageUrlMacro, array("target" => $target), $this->legacyMode,  false)
                                      . Macro::resolveMacros($this->thresholdImageUrlMacro, $thresholds, $this->legacyMode,false);

        $url = Url::fromPath('graphite', array(
            'graphite_url' => urlencode($largeImgUrl)
        ));

        $html = '<a href="%s" title="%s"><img src="%s" alt="%s" width="300" height="120" /></a>';

        return sprintf(
            $html,
            $url,
            $metric,
            $imgUrl,
            $metric
       );
    }

    /**
     *  Return threshold values for metrics.
     *
     *  @return array
     */
    private function getMetricThresholds($object, $metric) {
        foreach (PerfdataSet::fromString($object->perfdata)->asArray() as $pd) {
            if ($pd->getLabel() === $metric) {
                return array ( 
                    'warning'  => $pd->getWarningThreshold(),
                    'critical' => $pd->getcriticalThreshold() 
                );
            }
        }
    }
}
