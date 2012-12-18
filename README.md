# HTMLPurifier Add-ons

Addons for [HTMLPurifier](http://htmlpurifier.org/ "HTML Purifier - Filter your HTML the standards-compliant way!") library.

# Filters

## Iframe

Flexible content filter for granular control over iframes embedding.

[Discussion for Iframe plugin is here](http://htmlpurifier.org/phorum/read.php?2,6715).

### With this plugin you can:

1. Control which iframe tags will be preserved and which would be dropped.
2. Control what attributes are allowed for iframe tags.

### Plugin will decide to preserve or to drop iframe tag using following steps:

1. If iframe tag's src-attribute points to a URI which domain name is specified inside **allowed_domains** directive, then it should be preserved.

2. Plugin makes callback to a user function specified via **callback.is_uri_allowed** directive to decide whether to **PRESERVE** this iframe (function receives URI attribute).

3. Plugin makes callback to a user function specified via **callback.is_uri_denied** directive to decide whether to **DROP** this iframe (function receives URI attribute).

If steps 1-2 generates at least one **ALLOW** signal and step 3 doesn't generate **DENY** signal, then iframe tag will be preserved. In any other case it will be dropped.

This filtering scheme mimics classic *ALLOW-DENY-ORDER* firewall.

### Best practices:

- Use allowed domains to allow entire domain names (subdomains must be specified explicitly). Also see **uri.skip_www** directive.
- Use **ALLOW** callback function to allow more specific URI's.
- Finally use **DENY** callback function to deny some exceptional URI's.

Using this three schemes together you can achieve very granular and flexible filtering. But don't forget to heavily test your filtering stack before using it in production!

See **$default_options** static property for options documentation.

### Usage example:

    // Some content with <iframe> tags.
    $content = '...';

    /** @var HTMLPurifier_Config $purifierConfig */
    $purifierConfig = HTMLPurifier_Config::createDefault();
    
    // Setting filters.
    $iframeFilter = new HTMLPurifier_Filter_Betsol_Iframe(array(
        // Initially allowing everything from the YouTube's domain.
        'allowed_domains' => array(
            'youtube.com',
        ),
        
        // Allowing only specific URI's for SoundCloud and DailyMotion.
        'callback.is_uri_allowed' => function($uri) {
            if (
                   preg_match('@^https://w\.soundcloud\.com/player/@i',     $uri)
                || preg_match('@^http://(www\.)?dailymotion\.com/embed/@i', $uri)
            ) {
                return true;
            }
        },
        
        // And finally blocking a single video from the YouTube.
        'callback.is_uri_denied' => function($uri) {
            if (
               preg_match('@^http://(www\.)?youtube\.com/watch\?(.*?)&?v=IytNBm8WA1c@i', $uri)
            ) {
                return true;
            }
        },
    ));
    
    $purifierConfig->set('Filter.Custom', array(
        $iframeFilter,
    ));
    
    $purifier = new HTMLPurifier($purifierConfig);

    return $purifier->purify($content);

---
Slava Fomin II, CEO BETSOL GROUP  
Got any questions, ideas, propositions?  
Feel free to contact me at: <<s.fomin@betsol.ru>>  
From Russia with Love.  
Let's make this World a Better place!