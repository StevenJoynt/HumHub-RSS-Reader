<?php

namespace sij\humhub\modules\rss\components;

/**
 * SimpleXMLElement with some extra methods to make it easier to access optional values.
 */
class RssElement extends \SimpleXMLElement {

/**
 * Obtains the value of an attribute, or returns the supplied default value.
 */
    public function attr($name, $default = '', $ns = null, $is_prefix = false) {
        $attrs = $this->attributes($ns, $is_prefix);
        if ( isset($attrs[$name]) ) {
            $value = (string)$attrs[$name];
            $value = trim($value);
            if ( $value != '' ) return $value;
        }
        return $default;
    }

/**
 * Obtains the text enclosed in an element, or returns the supplied default value.
 */
    public function text($name, $default = '') {
        if ( isset($this->{$name}) ) {
            $value = (string)$this->{$name};
            $value = trim($value);
            if ( $value != '' ) return $value;
        }
        return $default;
    }

/**
 * Executes the callback function for each child element with the specified name
 */
    public function each($name, callable $callback) {
        foreach ( $this->children() as $key => $element ) {
            if ( $key == $name ) {
                call_user_func($callback, $element);
            }
        }
    }

}
