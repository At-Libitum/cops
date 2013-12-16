<?php
// would be a big issue if it wasn't set by now...
    if (!isset($config))
        $config = array();

    $config['default_timezone'] = "Europe/Amsterdam";

    /*
     * The directory containing calibre's metadata.db file, with sub-directories
     * containing all the formats.
     * BEWARE : it has to end with a /
     */
    $config['calibre_directory'] = array (
        "At libitum" => "Y:/Books/At_libitum/",
        "At libitum Src" => "Y:/Books/At_libitum_Src/",
        "COPS Test" => "Y:/Books/COPSTest/",
        "Main" => "Y:/Books/Main/",
        "Periodicals" => "Y:/Books/Periodicals/",
        "Plugin Testing" => "Y:/Books/PluginTesting/",
        "Testing" => "Y:/Books/Testing/",
        "ze WiP" => "Y:/Books/zeWorkInProgress/");

    $config['calibre_subtitles'] = array (
        "At libitum" => "Future Main Library",
        "At libitum Src" => "Main Library",
        "COPS Test" => "COPS Test Database",
        "Main" => "Supposedly a Main Library",
        "Periodicals" => "Sci-Fi/Fantasy Magazines and Periodicals",
        "PluginTesting" => "EMPTY: Books used in the testing of Stanza link",
        "Testing" => "ePub formatting and capability tests",
        "ze WiP" => "Books that need work");

    /*
     * Catalog's title
     */
    $config['cops_title_default'] = "Submit Search";
    $config['cops_subtitle_default'] = "Challenge Of PHP Scripting";

    $config['cops_full_url'] = 'http://deus.local/cops-submitsearch/';

    $config['cops_icon'] = "favicon.ico";

    /*
     * add support for user customization of html templates
     */
    $config['cops_template'] = "default";

    /*
     * allow setting a default startup style if no cookie present
     */
    $config['cops_style'] = "default";

    $config['cops_prefered_format'] = array ("EPUB", "DJVU", "PDF", "MOBI", "LIT", "LRF", "ZIP");

    $config['cops_max_item_per_page'] = 15;

    $config['cops_html_thumbnail_height'] = 240;
    $config['cops_opds_thumbnail_height'] = 80;
	$config['cops_thumbnail_handling'] = "";
    $config['cops_nocover_image'] = "bookcover_missing.png";

    $config['cops_html_tag_filter'] = 1;

    $config['cops_use_fancyapps'] = 1;

    $config['cops_use_url_rewriting'] = "0"; // see WiKi
    $config['cops_generate_invalid_opds_stream'] = 1;

    $config['cops_update_epub-metadata'] = "1";

    $config['cops_show_icons'] = 1;
    $config['cops_show_icons_html'] = 1;

    $config['cops_show_empty'] = "0";
    $config['cops_show_originals'] = 1;
    $config['cops_show_recent_bydate'] = 1;
    $config['cops_recentbooks_limit'] = 48;

    $config['cops_author_split_first_letter'] = 0;
    $config['cops_titles_split_first_letter'] = 0;

    // User Agents which don't understand the TypeAhead search :
    // Mercury on iPhone3:
    // Safari on iPhone3:  Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X) AppleWebKit/546.10 (KHTML, like Gecko) Version/6.0 Mobile/7E18WD Safari/8536.25

    // User Agents which do understand the TypeAhead search :
    // Mercury on iPad2: Mozilla/5.0 (iPad; CPU OS 6_0_1 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Mercury/7.2 Mobile/10A523 Safari/8536.25
    // Safari on iPad2:  Mozilla/5.0 (iPad; CPU OS 7_0_2 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A501 Safari/9537.53

    $config['cops_server_side_render'] = "7B334b|7E18WD|8B117|Kindle|EBRD1101|EBRD1201|cybook";

    $config['cops_books_filter'] = array (); // so far I only found this to work in KyBook

    $config['cops_calibre_custom_column'] = array ("collections", "storytype", "source", "country");
/*
    $config['cops_calibre_categories'] = array (
        "title",
        "authors",
        "author_series",
        "series",
        "collections",
        "publishers",
        "source",
        "storytype",
        "formats",
        "tags"
    );
*/
    $config['cops_mail_configuration'] = array( "smtp.host"     => "smtp.versatel.nl",
                                                "smtp.username" => "",
                                                "smtp.password" => "",
                                                "smtp.secure"   => "",
                                                "address.from"  => "raj.gabrielse@gmail.com"
                                                );
