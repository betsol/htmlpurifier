<?php

/**
 * Iframe Filter Plugin for HTMLPurifier.
 * 
 * @author: Slava Fomin II <s.fomin@betsol.ru>
 * From Russia with Love.
 * Let's make this World a Better place!
 */
class HTMLPurifier_Filter_Betsol_Iframe extends HTMLPurifier_Filter
{
    // Unique plugin name.
    public $name = 'betsol_iframe';
    
    // Default options.
    // You can override this using "options" argument of the constructor.
    protected static $default_options = array(
        // List of attributes that are allowed for iframe tag.
        'allowed_attributes' => array(
            'src',
            'width',
            'height',
            'frameborder',
            'allowfullscreen',
        ),
        
        // Whether to allow iframe with any src attribute.
        // This option takes first precedence.
        //
        // @author [s.fomin]: i don't recommend to use this option in production environment
        // as it defeats the purpose of this plugin. 
        'allow_any_uri' => false,
        
        'allowed_domains' => array(),
        
        // Whether to skip "www." subdomain when checking URI using allowed domains scheme.
        'uri.skip_www' => true,
        
        // User-specified callback-function to check iframes URI.
        // Return boolean TRUE if you want to ALLOW iframe with passed URI.
        'callback.is_uri_allowed' => null,

        // User-specified callback-function to check iframes URI.
        // Return boolean TRUE if you want to DENY iframe with passed URI.
        'callback.is_uri_denied' => null,
    );
    
    protected $options = null;
    
    protected $meta_token = '';

    /**
     * Constructs filter instance.
     * You can specify additional parameters using "options" argument.
     * 
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        // Merging default options with specified ones.
        $this->options = array_merge(self::$default_options, $options);
        
        // Generating random meta token.
        $this->meta_token = strtoupper($this->name) . '_' . mt_rand(0, pow(10, 9));
    }

    /**
     * Parses all iframes in the HTML source and replaces them with normalized meta-presentations.
     * 
     * @param string $html
     * @param $config
     * @param $context
     * @return string
     */
    public function preFilter($html, $config, $context)
    {
        // Removing all initial meta-presentations entered by the user as an additional security measure.
        $html = preg_replace('#@' . $this->meta_token . ':.*?@#', '', $html); 
        
        // Searching for all iframes first.
        $matches = array();
        preg_match_all('@<iframe.*?>.*?</iframe>@is', $html, $matches, PREG_SET_ORDER);
        
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $iframe_html = $match[0];
                
                // Extracting allowed attributes from this iframe.
                $src = null;
                $attrs = array();
                foreach ($this->options['allowed_attributes'] as $attr_name) {
                    $attr = $this->extractAttribute($attr_name, $iframe_html);
                    if ($attr) {
                        $attrs[] = $attr;
                    }
                    if ($attr_name == 'src') {
                        $src = $attr[1];
                    }
                }
                
                // Deciding whether to drop the iframe or to keep it.
                $drop_tag = true;
                if ($src !== null) {
                    // Checking iframe's URI.
                    if ($this->options['allow_any_uri']) {
                        $drop_tag = false;
                    } else {
                        
                        // Checking URI using allowed domains scheme.
                        $domain_name = parse_url($src, PHP_URL_HOST);
                        if ($this->options['uri.skip_www'] && substr($domain_name, 0, 4) === 'www.') {
                            $domain_name = substr($domain_name, 4);
                        }
                        if (in_array($domain_name, $this->options['allowed_domains'])) {
                            $drop_tag = false;
                        }
                        
                        // Checking URI using allow callback-function scheme.
                        if ($drop_tag && is_callable($this->options['callback.is_uri_allowed'])) {
                            if (call_user_func($this->options['callback.is_uri_allowed'], $src) === true) {
                                $drop_tag = false;
                            }
                        }
                        
                        // Finally checking URI using deny callback-function scheme.
                        if (!$drop_tag && is_callable($this->options['callback.is_uri_denied'])) {
                            if (call_user_func($this->options['callback.is_uri_denied'], $src) === true) {
                                $drop_tag = true;
                            }
                        }
                    }
                }
                
                if (!$drop_tag) {
                    // Generating meta-presentation for this iframe.
                    $meta_html = '@' . $this->meta_token . ':' . json_encode($attrs) . '@';
                    
                    // Replacing original iframe with meta-presentation.
                    $html = str_replace($iframe_html, $meta_html, $html);
                } else {
                    // Dropping iframe tag.
                    $html = str_replace($iframe_html, '', $html);
                }
            }
        }
        
        return $html;
    }

    /**
     * Converting all meta-presentations back to iframe tags.
     * 
     * @param $html
     * @param $config
     * @param $context
     * @return mixed
     */
    public function postFilter($html, $config, $context)
    {
        // Searching for meta-presentations.
        $matches = array();
        preg_match_all('#@' . $this->meta_token . ':(?P<attrs>.*?)@#', $html, $matches, PREG_SET_ORDER);
        
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $meta_html = $match[0];
                
                if (isset($match['attrs'])) {
                    // Decoding attributes.
                    $attrs = json_decode($match['attrs'], true);
                    
                    // Constructing ifrane tag.
                    $iframe_html = $this->reconstructIframeTag($attrs);
                    
                    // Replacing meta-presentation with proper iframe tag.
                    $html = str_replace($meta_html, $iframe_html, $html);
                    
                } else {
                    // If no attributes is found, just removing meta-presentation from the output.
                    $html = str_replace($meta_html, '', $html);
                }
            }
        }
        
        return $html;
    }

    /**
     * Extracts specified attribute from tag's HTML presentation.
     * Returns indexed array with two elements: [0] - attribute name, [1] - attribute value.
     * If attribute is not found this method will return NULL.
     * 
     * @param  string     $attr_name name of the attribute
     * @param  string     $tag_html  HTML presentation of the tag
     * @return array|null
     */
    protected function extractAttribute($attr_name, $tag_html)
    {
        $matches = array();
        preg_match(
            '@(?P<name>' . $attr_name . ')(?:\s*?=\s*?(?:"(?P<value1>.*?)"|(?P<value2>.*?)(?:\s|>|/>)))?@is',
            $tag_html, $matches
        );
        
        $result = null;
        $name = null;
        $value = null;
        if (isset($matches['name'])) {
            $name = $matches['name'];
            
            if (isset($matches['value1'])) {
                $value = $matches['value1'];
            } elseif (isset($matches['value2'])) {
                $value = $matches['value2'];
            }
            
            $result = array($name, $value);
        }
        
        return $result;
    }

    /**
     * Generates proper HTML-presentation of the iframe based on specified attributes.
     * See "extractAttribute()" method for attribute format specification.
     * 
     * @param  array  $attrs
     * @return string
     */
    protected function reconstructIframeTag(array $attrs)
    {
        $tag_name = 'iframe';
        $attributes = array();
        foreach ($attrs as $attr) {
            if (!is_array($attr) || count($attr) != 2) {
                continue;
            }
            $name  = $attr[0];
            $value = $attr[1];
            
            $attributes[] = ($value !== null ? $name . '="' . $value . '"' : $name);
        }
        
        return '<' . $tag_name . (count($attributes) ? ' ' . implode(' ', $attributes) : '') . '></' . $tag_name . '>';
    }
}