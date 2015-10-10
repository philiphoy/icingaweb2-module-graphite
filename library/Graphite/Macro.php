<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Graphite;

/**
 * Expand macros in string in the context of MonitoredObjects
 */
class Macro
{
    /**
     * Known icinga macros
     *
     * @var array
     */
    private static $icingaMacros = array(
        'HOSTNAME'     => 'host_name',
        'HOSTADDRESS'  => 'host_address',
        'SERVICEDESC'  => 'service_description',
        'service.name' => 'service_description'
    );

    /**
     * Return the given string with macros being resolved
     *
     * @param   string                      $input      The string in which to look for macros
     * @param   MonitoredObject|stdClass    $object     The host or service used to resolve macros
     *
     * @return  string                                  The substituted or unchanged string
     */
    public static function resolveMacros($input, $object, $escape = true)
    {
        $matches = array();
        if (preg_match_all('@\$([^\$\s]+)\$@', $input, $matches)) {
            foreach ($matches[1] as $key => $value) {
                $newValue = self::resolveMacro($value, $object);
                if ($newValue !== $value) {
                    if ($escape){
                      $newValue = self::escapeMetric($newValue, false);
                    }                    
                    $input = str_replace($matches[0][$key], $newValue, $input);
                }
            }
        }

        return $input;
    }


    /**
     * Resolve a macro based on the given object
     *
     * @param   string                      $macro      The macro to resolve
     * @param   MonitoredObject|stdClass    $object     The object used to resolve the macro
     *
     * @return  string                                  The new value or the macro if it cannot be resolved
     */
    public static function resolveMacro($macro, $object)
    {
        if (is_array($object) && array_key_exists($macro, $object)) {
            return $object[$macro];
        }

        if (array_key_exists($macro, self::$icingaMacros) && $object->{self::$icingaMacros[$macro]} !== false) {
            return $object->{self::$icingaMacros[$macro]};
        }

        if (array_key_exists($macro, $object->customvars)) {
            return $object->customvars[$macro];
        }

        $translated = self::translateTerm($macro);

        if (array_key_exists($translated, $object->customvars)) {
            return $object->customvars[$translated];
        }

        if  ($object->{$translated} !== false) {
            return $object->{$translated};
        }

        return $macro;
    }

    public static function escapeMetric($str)
    {       
        $str=str_replace('.','_',$str);        
        $str=str_replace('-','_',$str);
        $str=str_replace(' ','_',$str);
        $str=str_replace('\\','_',$str);
        $str=str_replace('/','_',$str);
        $str=str_replace('::','.',$str);
        return $str;
    }

    private static function translateTerm($term){

        if (substr($term, 0, strlen('host.vars.')) === 'host.vars.'){
            $term = str_replace('host.vars.','',$term);
            return ucwords(str_replace('_', ' ', strtolower($cv->varname)));
        }

        if (substr($term, 0, strlen('service.vars.')) === 'service.vars.'){
            $term = str_replace('service.vars.','',$term);
            return ucwords(str_replace('_', ' ', strtolower($cv->varname)));
        }

        if (substr($term, 0, strlen('service.')) === 'service.'){
            return str_replace('service.','service_',$term);
        }

        if (substr($term, 0, strlen('host.')) === 'host.'){
            return str_replace('host.','host_',$term);
        }     
    }
}
