<?php
/**
 *
 * @author
 * @copyright Copyright (c) 2013 Beijing Jinritemai Technology Co.,Ltd.
 */

namespace General\Filter;

class Xss {
    private $_allowHttpValue = false;
    private $_input;
    private $_pregPatterns = array(
        // Fix &entity\n
        '!(&#0+[0-9]+)!' => '$1;',
        '/(&#*\w+)[\x00-\x20]+;/u' => '$1;>',
        '/(&#x*[0-9A-F]+);*/iu' => '$1;',
        //any attribute starting with "on" or xmlns
        '#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu' => '$1>',
        //javascript: and vbscript: protocols
        '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu' => '$1=$2nojavascript...',
        '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu' => '$1=$2novbscript...',
        '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u' => '$1=$2nomozbinding...',
        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i' => '$1>',
        '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu' => '$1>',
        // namespaced elements
        '#</*\w+:\w[^>]*+>#i' => '',
        //unwanted tags
        '#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i' => ''
    );

    private $_normalPatterns = array(
        '\'' => '&lsquo;',
        '"' => '&quot;',
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;'
    );

    public function filterIt($in){
        $this->_input = html_entity_decode($in, ENT_NOQUOTES, 'UTF-8');
        $this->normalReplace();
        $this->doGrep();
        return $this->_input;
    }

    public function filterAll($in) {
        $ret = null;
        if ((is_array($in) && !empty($in)) || $in instanceof \Traversable) {
            foreach ($in as $key => $val) {
                $ret[$key] = $this->filterAll($val);
            }
        } else if (is_array($in)) {
            $ret =  array();
        } else {
            $ret =  $this->filterIt($in);
        }

        return $ret;
    }

    public function allowHttp(){
        $this->_allowHttpValue = true;
    }


    public function disallowHttp(){
        $this->_allowHttpValue = false;
    }

    private function normalReplace(){
        $this->_input = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $this->_input);
        if($this->_allowHttpValue == false){
            $this->_input = str_replace(array('%', 'script', 'localhost'), array('', '', '', '', ''), $this->_input);
        }
        else
        {
            $this->_input = str_replace(array('%', 'script', 'localhost','../'), array('', '', '', '',''), $this->_input);
        }
        foreach($this->_normalPatterns as $pattern => $replacement){
            $this->_input = str_replace($pattern,$replacement,$this->_input);
        }
    }

    private function doGrep(){
        foreach($this->_pregPatterns as $pattern => $replacement){
            $this->_input = preg_replace($pattern,$replacement,$this->_input);
        }
    }
}