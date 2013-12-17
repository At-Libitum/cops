<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

define ("VERSION", "0.7.0beta");
define ("DB", "db");
date_default_timezone_set($config['default_timezone']);


function useServerSideRendering () {
    global $config;
    return preg_match("/" . $config['cops_server_side_render'] . "/", $_SERVER['HTTP_USER_AGENT']);
}

function getQueryString () {
    if ( isset($_SERVER['QUERY_STRING']) ) {
        return $_SERVER['QUERY_STRING'];
    }
    return "";
}

function getURLParam ($name, $default = NULL) {
    if (!empty ($_GET) && isset($_GET[$name]) && $_GET[$name] != "") {
        return $_GET[$name];
    }
    return $default;
}

function getCurrentOption ($option) {
    global $config;
    if (isset($_COOKIE[$option])) {
        return $_COOKIE[$option];
    }
    if ($option == "style") {
        return "default";
    }

    if (isset($config ["cops_" . $option])) {
        return $config ["cops_" . $option];
    }

    return "";
}

function getCurrentCss () {
    return "styles/style-" . getCurrentOption ("style") . ".css";
}

function getUrlWithVersion ($url) {
    return $url . "?v=" . VERSION;
}

function xml2xhtml($xml) {
    return preg_replace_callback('#<(\w+)([^>]*)\s*/>#s', create_function('$m', '
        $xhtml_tags = array("br", "hr", "input", "frame", "img", "area", "link", "col", "base", "basefont", "param");
        return in_array($m[1], $xhtml_tags) ? "<$m[1]$m[2] />" : "<$m[1]$m[2]></$m[1]>";
    '), $xml);
}

function display_xml_error($error)
{
    $return .= str_repeat('-', $error->column) . "^\n";

    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "Warning $error->code: ";
            break;
         case LIBXML_ERR_ERROR:
            $return .= "Error $error->code: ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "Fatal Error $error->code: ";
            break;
    }

    $return .= trim($error->message) .
               "\n  Line: $error->line" .
               "\n  Column: $error->column";

    if ($error->file) {
        $return .= "\n  File: $error->file";
    }

    return "$return\n\n--------------------------------------------\n\n";
}

function are_libxml_errors_ok ()
{
    $errors = libxml_get_errors();

    foreach ($errors as $error) {
        if ($error->code == 801) return false;
    }
    return true;
}

function html2xhtml ($html) {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);

    $doc->loadHTML('<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"></head><body>' .
                        $html  . '</body></html>'); // Load the HTML
    $output = $doc->saveXML($doc->documentElement); // Transform to an Ansi xml stream
    $output = xml2xhtml($output);
    if (preg_match ('#<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"></meta></head><body>(.*)</body></html>#ms', $output, $matches)) {
        $output = $matches [1]; // Remove <html><body>
    }
    /*
    // In case of error with summary, use it to debug
    $errors = libxml_get_errors();

    foreach ($errors as $error) {
        $output .= display_xml_error($error);
    }
    */

    if (!are_libxml_errors_ok ()) $output = "HTML code not valid.";

    libxml_use_internal_errors(false);
    return $output;
}

/**
 * This method is a direct copy-paste from
 * http://tmont.com/blargh/2010/1/string-format-in-php
 */
function str_format($format) {
    $args = func_get_args();
    $format = array_shift($args);

    preg_match_all('/(?=\{)\{(\d+)\}(?!\})/', $format, $matches, PREG_OFFSET_CAPTURE);
    $offset = 0;
    foreach ($matches[1] as $data) {
        $i = $data[0];
        $format = substr_replace($format, @$args[$i], $offset + $data[1] - 1, 2 + strlen($i));
        $offset += strlen(@$args[$i]) - 2 - strlen($i);
    }

    return $format;
}

/**
 * This method is based on this page
 * http://www.mind-it.info/2010/02/22/a-simple-approach-to-localization-in-php/
 */
function localize($phrase, $count=-1, $reset=false) {
    if ($count == 0)
        $phrase .= ".none";
    if ($count == 1)
        $phrase .= ".one";
    if ($count > 1)
        $phrase .= ".many";

    /* Static keyword is used to ensure the file is loaded only once */
    static $translations = NULL;
    if ($reset) {
        $translations = NULL;
    }
    /* If no instance of $translations has occured load the language file */
    if (is_null($translations)) {
        $lang = "en";
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        {
            $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        }
        $lang_file_en = NULL;
        $lang_file = dirname(__FILE__). '/lang/Localization_' . $lang . '.json';
        if (!file_exists($lang_file)) {
            $lang_file = dirname(__FILE__). '/lang/' . 'Localization_en.json';
        }
        elseif ($lang != "en") {
            $lang_file_en = dirname(__FILE__). '/lang/' . 'Localization_en.json';
        }
        $lang_file_content = file_get_contents($lang_file);
        /* Load the language file as a JSON object and transform it into an associative array */
        $translations = json_decode($lang_file_content, true);

        /* Clean the array of all unfinished translations */
        foreach (array_keys ($translations) as $key) {
            if (preg_match ("/^##TODO##/", $key)) {
                unset ($translations [$key]);
            }
        }
        if ($lang_file_en)
        {
            $lang_file_content = file_get_contents($lang_file_en);
            $translations_en = json_decode($lang_file_content, true);
            $translations = array_merge ($translations_en, $translations);
        }
    }
    if (array_key_exists ($phrase, $translations)) {
        return $translations[$phrase];
    }
    return $phrase;
}

function addURLParameter($urlParams, $paramName, $paramValue) {
    if (empty ($urlParams)) {
        $urlParams = "";
    }
    $start = "";
    if (preg_match ("#^\?(.*)#", $urlParams, $matches)) {
        $start = "?";
        $urlParams = $matches[1];
    }
    $params = array();
    parse_str($urlParams, $params);
    if (empty ($paramValue) && $paramValue != 0) {
        unset ($params[$paramName]);
    } else {
        $params[$paramName] = $paramValue;
    }
    return $start . http_build_query($params);
}

class Link
{
    const OPDS_THUMBNAIL_TYPE = "http://opds-spec.org/image/thumbnail";
    const OPDS_IMAGE_TYPE = "http://opds-spec.org/image";
    const OPDS_ACQUISITION_TYPE = "http://opds-spec.org/acquisition";
    const OPDS_NAVIGATION_TYPE = "application/atom+xml;profile=opds-catalog;kind=navigation";
    const OPDS_PAGING_TYPE = "application/atom+xml;profile=opds-catalog;kind=acquisition";

    public $href;
    public $type;
    public $rel;
    public $title;
    public $facetGroup;
    public $activeFacet;

    public function __construct($phref, $ptype, $prel = NULL, $ptitle = NULL, $pfacetGroup = NULL, $pactiveFacet = FALSE) {
        $this->href = $phref;
        $this->type = $ptype;
        $this->rel = $prel;
        $this->title = $ptitle;
        $this->facetGroup = $pfacetGroup;
        $this->activeFacet = $pactiveFacet;
    }

    public function hrefXhtml () {
        return $this->href;
    }
}

class LinkNavigation extends Link
{
    public function __construct($phref, $prel = NULL, $ptitle = NULL) {
        parent::__construct ($phref, Link::OPDS_NAVIGATION_TYPE, $prel, $ptitle);
        if (!is_null (GetUrlParam (DB))) $this->href = addURLParameter ($this->href, DB, GetUrlParam (DB));
        if (!preg_match ("#^\?(.*)#", $this->href) && !empty ($this->href)) $this->href = "?" . $this->href;
        if (preg_match ("/(bookdetail|getJSON).php/", $_SERVER["SCRIPT_NAME"])) {
            $this->href = "index.php" . $this->href;
        } else {
            $this->href = $_SERVER["SCRIPT_NAME"] . $this->href;
        }
    }
}

class LinkFacet extends Link
{
    public function __construct($phref, $ptitle = NULL, $pfacetGroup = NULL, $pactiveFacet = FALSE) {
        parent::__construct ($phref, Link::OPDS_PAGING_TYPE, "http://opds-spec.org/facet", $ptitle, $pfacetGroup, $pactiveFacet);
        if (!is_null (GetUrlParam (DB))) $this->href = addURLParameter ($this->href, DB, GetUrlParam (DB));
        $this->href = $_SERVER["SCRIPT_NAME"] . $this->href;
    }
}

class Entry
{
    public $title;
    public $id;
    public $content;
    public $contentType;
    public $linkArray;
    public $localUpdated;
    private static $updated = NULL;

    public static $icons = array(
        Author::ALL_AUTHORS_ID       => 'images/author.png',
        Serie::ALL_SERIES_ID         => 'images/serie.png',
        Book::ALL_RECENT_BOOKS_ID    => 'images/recent.png',
        Tag::ALL_TAGS_ID             => 'images/tag.png',
        Language::ALL_LANGUAGES_ID   => 'images/language.png',
        CustomColumn::ALL_CUSTOMS_ID => 'images/tag.png',
        "cops:books$"             => 'images/allbook.png',
        "cops:books:letter"       => 'images/allbook.png',
        Publisher::ALL_PUBLISHERS_ID => 'images/publisher.png'
    );

    public function getUpdatedTime () {
        if (!is_null ($this->localUpdated)) {
            return date (DATE_ATOM, $this->localUpdated);
        }
        if (is_null (self::$updated)) {
            self::$updated = time();
        }
        return date (DATE_ATOM, self::$updated);
    }

    public function getNavLink () {
        foreach ($this->linkArray as $link) {
            if ($link->type != Link::OPDS_NAVIGATION_TYPE) { continue; }

            return $link->hrefXhtml ();
        }
        return "#";
    }

    public function getContentArray () {
        return array ( "title" => $this->title, "content" => $this->content, "navlink" => $this->getNavLink () );
    }

    public function __construct($ptitle, $pid, $pcontent, $pcontentType, $plinkArray) {
        global $config;
        $this->title = $ptitle;
        $this->id = $pid;
        $this->content = $pcontent;
        $this->contentType = $pcontentType;
        $this->linkArray = $plinkArray;

        if ($config['cops_show_icons'] == 1)
        {
            foreach (self::$icons as $reg => $image)
            {
                if (preg_match ("/" . $reg . "/", $pid)) {
                    array_push ($this->linkArray, new Link (getUrlWithVersion ($image), "image/png", Link::OPDS_THUMBNAIL_TYPE));
                    break;
                }
            }
        }

        if (!is_null (GetUrlParam (DB))) $this->id = str_replace ("cops:", "cops:" . GetUrlParam (DB) . ":", $this->id);
    }
}

class EntryBook extends Entry
{
    public $book;

    public function __construct($ptitle, $pid, $pcontent, $pcontentType, $plinkArray, $pbook) {
        parent::__construct ($ptitle, $pid, $pcontent, $pcontentType, $plinkArray);
        $this->book = $pbook;
        $this->localUpdated = $pbook->timestamp;
    }

    public function getContentArray () {
        $entry = array ( "title" => $this->title);
        $entry ["book"] = $this->book->getContentArray ();
        return $entry;
    }

    public function getCoverThumbnail () {
        foreach ($this->linkArray as $link) {
            if ($link->rel == Link::OPDS_THUMBNAIL_TYPE)
                return $link->hrefXhtml ();
        }
        return null;
    }

    public function getCover () {
        foreach ($this->linkArray as $link) {
            if ($link->rel == Link::OPDS_IMAGE_TYPE)
                return $link->hrefXhtml ();
        }
        return null;
    }
}

class Page
{
    public $title;
    public $subtitle = "";
    public $authorName = "";
    public $authorUri = "";
    public $authorEmail = "";
    public $idPage;
    public $idGet;
    public $query;
    public $favicon;
    public $n;
    public $book;
    public $totalNumber = -1;
    public $entryArray = array();

    public static function getPage ($pageId, $id, $query, $n)
    {
        switch ($pageId) {
            case Base::PAGE_ALL_AUTHORS :
                return new PageAllAuthors ($id, $query, $n);
            case Base::PAGE_AUTHORS_FIRST_LETTER :
                return new PageAllAuthorsLetter ($id, $query, $n);
            case Base::PAGE_AUTHOR_DETAIL :
                return new PageAuthorDetail ($id, $query, $n);
            case Base::PAGE_ALL_TAGS :
                return new PageAllTags ($id, $query, $n);
            case Base::PAGE_TAG_DETAIL :
                return new PageTagDetail ($id, $query, $n);
            case Base::PAGE_ALL_LANGUAGES :
                return new PageAllLanguages ($id, $query, $n);
            case Base::PAGE_LANGUAGE_DETAIL :
                return new PageLanguageDetail ($id, $query, $n);
            case Base::PAGE_ALL_CUSTOMS :
                return new PageAllCustoms ($id, $query, $n);
            case Base::PAGE_CUSTOM_DETAIL :
                return new PageCustomDetail ($id, $query, $n);
            case Base::PAGE_ALL_SERIES :
                return new PageAllSeries ($id, $query, $n);
            case Base::PAGE_ALL_BOOKS :
                return new PageAllBooks ($id, $query, $n);
            case Base::PAGE_ALL_BOOKS_LETTER:
                return new PageAllBooksLetter ($id, $query, $n);
            case Base::PAGE_ALL_RECENT_BOOKS :
                return new PageRecentBooks ($id, $query, $n);
            case Base::PAGE_SERIE_DETAIL :
                return new PageSerieDetail ($id, $query, $n);
            case Base::PAGE_OPENSEARCH_QUERY :
                return new PageQueryResult ($id, $query, $n);
            case Base::PAGE_BOOK_DETAIL :
                return new PageBookDetail ($id, $query, $n);
            case Base::PAGE_ALL_PUBLISHERS:
                return new PageAllPublishers ($id, $query, $n);
            case Base::PAGE_PUBLISHER_DETAIL :
                return new PagePublisherDetail ($id, $query, $n);
            case Base::PAGE_ABOUT :
                return new PageAbout ($id, $query, $n);
            case Base::PAGE_CUSTOMIZE :
                return new PageCustomize ($id, $query, $n);
            default:
                $page = new Page ($id, $query, $n);
                $page->idPage = "cops:catalog";
                return $page;
        }
    }

    public function __construct($pid, $pquery, $pn) {
        global $config;

        $this->idGet = $pid;
        $this->query = $pquery;
        $this->n = $pn;
        $this->favicon = $config['cops_icon'];
        $this->authorName = empty($config['cops_author_name']) ? utf8_encode('Sébastien Lucas') : $config['cops_author_name'];
        $this->authorUri = empty($config['cops_author_uri']) ? 'http://blog.slucas.fr' : $config['cops_author_uri'];
        $this->authorEmail = empty($config['cops_author_email']) ? 'sebastien@slucas.fr' : $config['cops_author_email'];
    }

    public function InitializeContent ()
    {
        global $config;
        $this->title = $config['cops_title_default'];
        $this->subtitle = $config['cops_subtitle_default'];
        if (Base::noDatabaseSelected ()) {
            $i = 0;
            foreach (Base::getDbNameList () as $key) {
                $nBooks = Book::getBookCount ($i);
                array_push ($this->entryArray, new Entry ($key, "cops:{$i}:catalog",
                                        str_format (localize ("bookword", $nBooks), $nBooks), "text",
                                        array ( new LinkNavigation ("?" . DB . "={$i}"))));
                $i++;
                Base::clearDb ();
            }
        } else {
            array_push ($this->entryArray, Author::getCount());
            $series = Serie::getCount();
            if (!is_null ($series)) array_push ($this->entryArray, $series);

            $publisher = Publisher::getCount();
            if (!is_null ($publisher)) array_push ($this->entryArray, $publisher);

            $tags = Tag::getCount();
            if (!is_null ($tags)) array_push ($this->entryArray, $tags);
            $languages = Language::getCount();
            if (!is_null ($languages)) array_push ($this->entryArray, $languages);
            foreach ($config['cops_calibre_custom_column'] as $lookup) {
                $customId = CustomColumn::getCustomId ($lookup);
                if (!is_null ($customId)) {
                    array_push ($this->entryArray, CustomColumn::getCount($customId));
                }
            }
            $this->entryArray = array_merge ($this->entryArray, Book::getCount());

            if (Base::isMultipleDatabaseEnabled ()) $this->title =  Base::getDbName ();
        }
    }

    public function isPaginated ()
    {
        return (getCurrentOption ("max_item_per_page") != -1 &&
                $this->totalNumber != -1 &&
                $this->totalNumber > getCurrentOption ("max_item_per_page"));
    }

    public function getNextLink ()
    {
        $currentUrl = getQueryString ();
        $currentUrl = preg_replace ("/\&n=.*?$/", "", "?" . getQueryString ());
        if (($this->n) * getCurrentOption ("max_item_per_page") < $this->totalNumber) {
            return new LinkNavigation ($currentUrl . "&n=" . ($this->n + 1), "next", localize ("paging.next.alternate"));
        }
        return NULL;
    }

    public function getPrevLink ()
    {
        $currentUrl = getQueryString ();
        $currentUrl = preg_replace ("/\&n=.*?$/", "", "?" . getQueryString ());
        if ($this->n > 1) {
            return new LinkNavigation ($currentUrl . "&n=" . ($this->n - 1), "previous", localize ("paging.previous.alternate"));
        }
        return NULL;
    }

    public function getMaxPage ()
    {
        return ceil ($this->totalNumber / getCurrentOption ("max_item_per_page"));
    }

    public function containsBook ()
    {
        if (count ($this->entryArray) == 0) return false;
        if (get_class ($this->entryArray [0]) == "EntryBook") return true;
        return false;
    }

}

class PageAllAuthors extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize("authorword.title");
        if (getCurrentOption ("author_split_first_letter") == 1) {
            $this->entryArray = Author::getAllAuthorsByFirstLetter();
        }
        else {
            $this->entryArray = Author::getAllAuthors();
        }
        $this->idPage = Author::ALL_AUTHORS_ID;
    }
}

class PageAllAuthorsLetter extends Page
{
    public function InitializeContent ()
    {
        $this->idPage = Author::getEntryIdByLetter ($this->idGet);
        $this->entryArray = Author::getAuthorsByStartingLetter ($this->idGet);
        $this->title = str_format (localize ("splitByLetter.letter"), str_format (localize ("authorword", count ($this->entryArray)), count ($this->entryArray)), $this->idGet);
    }
}

class PageAuthorDetail extends Page
{
    public function InitializeContent ()
    {
        $author = Author::getAuthorById ($this->idGet);
        $this->idPage = $author->getEntryId ();
        $this->title = $author->name;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByAuthor ($this->idGet, $this->n);
    }
}

class PageAllPublishers extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize("publisherword.title");
        $this->entryArray = Publisher::getAllPublishers();
        $this->idPage = Publisher::ALL_PUBLISHERS_ID;
    }
}

class PagePublisherDetail extends Page
{
    public function InitializeContent ()
    {
        $publisher = Publisher::getPublisherById ($this->idGet);
        $this->title = $publisher->name;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByPublisher ($this->idGet, $this->n);
        $this->idPage = $publisher->getEntryId ();
    }
}

class PageAllTags extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize("tagword.title");
        $this->entryArray = Tag::getAllTags();
        $this->idPage = Tag::ALL_TAGS_ID;
    }
}

class PageAllLanguages extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize("languages.title");
        $this->entryArray = Language::getAllLanguages();
        $this->idPage = Language::ALL_LANGUAGES_ID;
    }
}

class PageCustomDetail extends Page
{
    public function InitializeContent ()
    {
        $customId = getURLParam ("custom", NULL);
        $custom = CustomColumn::getCustomById ($customId, $this->idGet);
        $this->idPage = $custom->getEntryId ();
        $this->title = $custom->name;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByCustom ($customId, $this->idGet, $this->n);
    }
}

class PageAllCustoms extends Page
{
    public function InitializeContent ()
    {
        $customId = getURLParam ("custom", NULL);
        $this->title = CustomColumn::getAllTitle ($customId);
        $this->entryArray = CustomColumn::getAllCustoms($customId);
        $this->idPage = CustomColumn::getAllCustomsId ($customId);
    }
}

class PageTagDetail extends Page
{
    public function InitializeContent ()
    {
        $tag = Tag::getTagById ($this->idGet);
        $this->idPage = $tag->getEntryId ();
        $this->title = $tag->name;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByTag ($this->idGet, $this->n);
    }
}

class PageLanguageDetail extends Page
{
    public function InitializeContent ()
    {
        $language = Language::getLanguageById ($this->idGet);
        $this->idPage = $language->getEntryId ();
        $this->title = $language->lang_code;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByLanguage ($this->idGet, $this->n);
    }
}

class PageAllSeries extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize("seriesword.title");
        $this->entryArray = Serie::getAllSeries();
        $this->idPage = Serie::ALL_SERIES_ID;
    }
}

class PageSerieDetail extends Page
{
    public function InitializeContent ()
    {
        $serie = Serie::getSerieById ($this->idGet);
        $this->title = $serie->name;
        list ($this->entryArray, $this->totalNumber) = Book::getBooksBySeries ($this->idGet, $this->n);
        $this->idPage = $serie->getEntryId ();
    }
}

class PageAllBooks extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize ("allbooks.title");
        if (getCurrentOption ("titles_split_first_letter") == 1) {
            $this->entryArray = Book::getAllBooks();
        }
        else {
            list ($this->entryArray, $this->totalNumber) = Book::getBooks ($this->n);
        }
        $this->idPage = Book::ALL_BOOKS_ID;
    }
}

class PageAllBooksLetter extends Page
{
    public function InitializeContent ()
    {
        list ($this->entryArray, $this->totalNumber) = Book::getBooksByStartingLetter ($this->idGet, $this->n);
        $this->idPage = Book::getEntryIdByLetter ($this->idGet);

        $count = $this->totalNumber;
        if ($count == -1)
            $count = count ($this->entryArray);

        $this->title = str_format (localize ("splitByLetter.letter"), str_format (localize ("bookword", $count), $count), $this->idGet);
    }
}

class PageRecentBooks extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize ("recent.title");
        $this->entryArray = Book::getAllRecentBooks ();
        $this->idPage = Book::ALL_RECENT_BOOKS_ID;
    }
}

class PageQueryResult extends Page
{
    const SCOPE_TAG = "tag";
    const SCOPE_SERIES = "series";
    const SCOPE_AUTHOR = "author";
    const SCOPE_BOOK = "book";
    const SCOPE_PUBLISHER = "publisher";

    public function InitializeContent ()
    {
        $pagequery = Base::PAGE_OPENSEARCH_QUERY;
        $database = getURLParam (DB);

        if ($this->idGet == -1) { // last argument - FALSE controls typeahead format data returned
            $this->entryArray = self::executeSearch($pagequery, $this->query, $database, false);
            return $this->entryArray;
        }

        $scope = getURLParam ("scope");
        if (empty ($scope)) {
            $this->title = str_format (localize ("search.result"), $this->query);
        } else {
            // Comment to help the perl i18n script
            // str_format (localize ("search.result.author"), $this->query)
            // str_format (localize ("search.result.tag"), $this->query)
            // str_format (localize ("search.result.series"), $this->query)
            // str_format (localize ("search.result.book"), $this->query)
            // str_format (localize ("search.result.publisher"), $this->query)
            $this->title = str_format (localize ("search.result.{$scope}"), $this->query);
        }

// across libraries search
        $crit = "%" . $this->query . "%";

        $dbArray = array ("");
        $d = $database; 

        // Special case when no databases were chosen, we search on all databases
        if (Base::noDatabaseSelected ()) {
            $dbArray = Base::getDbNameList ();
            $d = 0;

            foreach ($dbArray as $key) {
                Base::clearDb ();
                $nBooks = Book::getBookCount ($d);
                // needs safeguarding for empty db
                if ($nBooks > 0) { 
// consider refactoring getBooksByQuery into this class and make it a separate Search class
                    list ($array, $totalNumber) = Book::getBooksByQuery (array ("all" => $crit), 1, $d, 1);
                    array_push ($this->entryArray, new Entry ($key, DB . ":query:{$d}",
                                str_format (localize ("bookword", $totalNumber), $totalNumber), "text",
                                array ( new LinkNavigation ("?db={$d}&page={$pagequery}&query=" . $this->query))));
                }
                $d++;
            }
            return;
        }
// single category search
        switch ($scope) {
            case self::SCOPE_AUTHOR :
                $this->entryArray = Author::getAuthorsByStartingLetter ('%' . $this->query);
                break;
            case self::SCOPE_TAG :
                list ($this->entryArray, $this->totalNumber) = Tag::getAllTagsByQuery ($this->query, -1);
                break;
            case self::SCOPE_SERIES :
                $this->entryArray = Serie::getAllSeriesByQuery ($this->query);
                break;
            case self::SCOPE_BOOK :
                list ($this->entryArray, $this->totalNumber) = Book::getBooksByQuery (
                    array ("book" => $crit), $this->n);
                break;
            case self::SCOPE_PUBLISHER :
                $this->entryArray = Publisher::getAllPublishersByQuery ($this->query);
                break;
// across categories search
            default: // last argument - TRUE controls submit format data returned
                $this->entryArray = self::executeSearch($pagequery, $this->query, $d, true);
        }
    }

    private function doSearch ($query, $database) {
        global $config;

        if (Base::noDatabaseSelected ()) {
            // clear whichever may be the current library if multi-library searching
            Base::clearDb();
        }
        // otherwise use the current library.
        $searchdb = null;
        $nBooks = 0;
        $searchdb = Base::getDb($database);
        // in case we get an error in the db, but then we're likely not getting this far.
        if (!is_null ($searchdb)) {
            $nBooks = Book::getBookCount ($database);
        }
        elseif ((is_null ($searchdb)) || ($nBooks == 0))
        {   // safeguard against empty (if not wanting to show), non-existing or off-line database
            return array (-1, 0, 0, 0, 0, 0);
        }

        if (!in_array ("book", $config ['cops_ignored_search_scope'])) {
            $arrayBook = Book::getBooksByStartingLetter ('%' . $query, 1, NULL, 5);
        }
        if (!in_array ("author", $config ['cops_ignored_search_scope'])) {
            $arrayAuthor = Author::getAuthorsByStartingLetter ('%' . $query);
        }
        if (!in_array ("series", $config ['cops_ignored_search_scope'])) {
            $arraySeries = Serie::getAllSeriesByQuery ($query);
        }
        if (!in_array ("tag", $config ['cops_ignored_search_scope'])) {
            $arrayTag = Tag::getAllTagsByQuery ($query, 1, NULL, 5);
        }
        if (!in_array ("publisher", $config ['cops_ignored_search_scope'])) {
            $arrayPublisher = Publisher::getAllPublishersByQuery ($query);
        }
        $overalTotal = ((count ($arrayBook) == 2 && is_array ($arrayBook[0])) ? $arrayBook[1] : 0)
                     + count ($arrayAuthor)
                     + count ($arraySeries)
                     + ((count ($arrayTag) == 2 && is_array ($arrayTag[0])) ? $arrayTag[1] : 0)
                     + count ($arrayPublisher);

        return array ($overalTotal, $arrayBook, $arrayAuthor, $arraySeries, $arrayTag, $arrayPublisher);
    }

    // executeSearch replaces the typeahead search, formerly part of the Book::getJson function
    // and now also executes submitted searches on library or category selection screens.
    // the state of $submitted determines if the results are returned as dropdown search results
    // this does currently mean that if enter is pressed immediately, it executes twice in a row
    // but since both methods have too much common code, splitting would cause too much redundency
    // Another thing is, wanting to use {$key}word.title, I noticed that not every category had one.
    // Those without one did however had a regular title, and some had both with identical values.
    // So, rather than adding new ones, I decided to refactor the existing {&key}.title entries
    // into {$key}word.title entries if such entry did not exist. This rename was rippled through
    // to all other localization files to not inconvenience the translators. Left behind {$key}.title
    // entries that were no longer referenced as a result of the refactoring, have been removed.
    public function executeSearch($pagequery, $query, $database, $submitted = FALSE) {

        $havedb = true;
        $dbCount = 1;
        $entryArray = array ();

        $dbArray = array ("");
        $d = $database; 

        // Special case when no databases were chosen, we search on all databases
        if (Base::noDatabaseSelected ()) {
            $dbArray = Base::getDbNameList ();
            $d = 0;
            $havedb = false;
            $dbCount = count ($dbArray);
        }

        foreach ($dbArray as $dbkey) {

            $overalTotal = self::doSearch ($query, $d);
            // only if there's something to process
            if (is_array ($overalTotal) && $overalTotal[0] > 0) {

                if (!$submitted && !$havedb) {
                   array_push ($entryArray, array ("class" => "tt-header",
                                                   "title" => $dbkey . " (" . $overalTotal[0] . ")",
                                                   "navlink" => "getJSON.php?page={$pagequery}&current=index&query={$query}&db={$d}"));
                }

                foreach (array ("book" => $overalTotal[1],
                                "author" => $overalTotal[2],
                                "series" => $overalTotal[3],
                                "tag" => $overalTotal[4],
                                "publisher" => $overalTotal[5]) as $key => $array) {

                    // array for use in submitted search
                    $pages = array(
                                "book" => Book::ALL_BOOKS_ID,
                                "author" => Author::ALL_AUTHORS_ID,
                                "series" => Serie::ALL_SERIES_ID,
                                "tag" =>  Tag::ALL_TAGS_ID,
                                "publisher" => Publisher::ALL_PUBLISHERS_ID);

                    $i = 0;
                    if (count ($array) == 2 && is_array ($array [0])) {
                        $total = $array [1];
                        $array = $array [0];
                    } else {
                        $total = count ($array);
                    }
                    if ($total > 0) {
                        // Comment to help the perl i18n script
                        // str_format (localize("bookword", count($array))
                        // str_format (localize("authorword", count($array))
                        // str_format (localize("seriesword", count($array))
                        // str_format (localize("tagword", count($array))
                        // str_format (localize("publisherword", count($array))
                        if ($submitted) {
                            array_push ($entryArray, new Entry (localize ("{$key}word.title"), $pages[$key],
                                        str_format (localize ("{$key}word", $total), $total), "text",
                                        array ( new LinkNavigation ("?page={$pagequery}&query={$query}&db={$d}&scope={$key}"))));
                        }
                        else
                        {
                            array_push ($entryArray, array ("class" => ($havedb) ? "tt-header" : "tt-text",
                                        "title" => str_format (localize("{$key}word", $total), $total),
                                        "navlink" => "index.php?page={$pagequery}&query={$query}&db={$d}&scope={$key}"));
                        }
                    }

// okay, typeahead part of search limited to total counts per category on library selection page
// so this part only executes within a library. So it never searches beyond the 'next' lower level.
                    if (!$submitted && $havedb) {
                        foreach ($array as $entry) {
                            if ($entry instanceof EntryBook) {
                                array_push ($entryArray, array ("class" => "tt-text", "title" => $entry->title, "navlink" => $entry->book->getDetailUrl () . "&db={$d}" ));
                            } else {
                                array_push ($entryArray, array ("class" => "tt-text", "title" => $entry->title, "navlink" => $entry->getNavLink () . "&db={$d}" ));
                            }
                            $i++;
                            if ($i > 4) { break; }; // safeguard for category arrays.
                        }
                    }
                }
            }
            $d++;
            if ($d+1 > $dbCount) { break; }; // force loop break if bigger
        }
        return $entryArray;
    }
}

class PageBookDetail extends Page
{
    public function InitializeContent ()
    {
        $this->book = Book::getBookById ($this->idGet);
        $this->title = $this->book->title;
    }
}

class PageAbout extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize ("about.title");
    }
}

class PageCustomize extends Page
{
    public function InitializeContent ()
    {
        $this->title = localize ("customize.title");
        $this->entryArray = array ();

        $use_fancybox = "";
        if (getCurrentOption ("use_fancyapps") == 1) {
            $use_fancybox = "checked='checked'";
        }
        $html_tag_filter = "";
        if (getCurrentOption ("html_tag_filter") == 1) {
            $html_tag_filter = "checked='checked'";
        }


        $content = "";
        if (!preg_match("/(Kobo|Kindle\/3.0|EBRD1101)/", $_SERVER['HTTP_USER_AGENT'])) {
            $content .= '<select id="style" onchange="updateCookie (this);">';
        }
            foreach (glob ("styles/style-*.css") as $filename) {
                if (preg_match ('/styles\/style-(.*?)\.css/', $filename, $m)) {
                    $filename = $m [1];
                }
                $selected = "";
                if (getCurrentOption ("style") == $filename) {
                    if (!preg_match("/(Kobo|Kindle\/3.0|EBRD1101)/", $_SERVER['HTTP_USER_AGENT'])) {
                        $selected = "selected='selected'";
                    } else {
                        $selected = "checked='checked'";
                    }
                }
                if (!preg_match("/(Kobo|Kindle\/3.0|EBRD1101)/", $_SERVER['HTTP_USER_AGENT'])) {
                    $content .= "<option value='{$filename}' {$selected}>{$filename}</option>";
                } else {
                    $content .= "<input type='radio' onchange='updateCookieFromCheckbox (this);' id='style-{$filename}' name='style' value='{$filename}' {$selected} /><label for='style-{$filename}'> {$filename} </label>";
                }
            }
        if (!preg_match("/(Kobo|Kindle\/3.0|EBRD1101)/", $_SERVER['HTTP_USER_AGENT'])) {
            $content .= '</select>';
        }
        array_push ($this->entryArray, new Entry (localize ("customize.style"), "",
                                        $content, "text",
                                        array ()));
        if (!useServerSideRendering ()) {
            $content = '<input type="checkbox" onchange="updateCookieFromCheckbox (this);" id="use_fancyapps" ' . $use_fancybox . ' />';
            array_push ($this->entryArray, new Entry (localize ("customize.fancybox"), "",
                                            $content, "text",
                                            array ()));
        }
        $content = '<input type="number" onchange="updateCookie (this);" id="max_item_per_page" value="' . getCurrentOption ("max_item_per_page") . '" min="-1" max="1200" pattern="^[-+]?[0-9]+$" />';
        array_push ($this->entryArray, new Entry (localize ("customize.paging"), "",
                                        $content, "text",
                                        array ()));
        $content = '<input type="text" onchange="updateCookie (this);" id="email" value="' . getCurrentOption ("email") . '" />';
        array_push ($this->entryArray, new Entry (localize ("customize.email"), "",
                                        $content, "text",
                                        array ()));
        $content = '<input type="checkbox" onchange="updateCookieFromCheckbox (this);" id="html_tag_filter" ' . $html_tag_filter . ' />';
        array_push ($this->entryArray, new Entry (localize ("customize.filter"), "",
                                        $content, "text",
                                        array ()));
    }
}


abstract class Base
{
    const PAGE_INDEX = "index";
    const PAGE_ALL_AUTHORS = "1";
    const PAGE_AUTHORS_FIRST_LETTER = "2";
    const PAGE_AUTHOR_DETAIL = "3";
    const PAGE_ALL_BOOKS = "4";
    const PAGE_ALL_BOOKS_LETTER = "5";
    const PAGE_ALL_SERIES = "6";
    const PAGE_SERIE_DETAIL = "7";
    const PAGE_OPENSEARCH = "8";
    const PAGE_OPENSEARCH_QUERY = "9";
    const PAGE_ALL_RECENT_BOOKS = "10";
    const PAGE_ALL_TAGS = "11";
    const PAGE_TAG_DETAIL = "12";
    const PAGE_BOOK_DETAIL = "13";
    const PAGE_ALL_CUSTOMS = "14";
    const PAGE_CUSTOM_DETAIL = "15";
    const PAGE_ABOUT = "16";
    const PAGE_ALL_LANGUAGES = "17";
    const PAGE_LANGUAGE_DETAIL = "18";
    const PAGE_CUSTOMIZE = "19";
    const PAGE_ALL_PUBLISHERS = "20";
    const PAGE_PUBLISHER_DETAIL = "21";

    const COMPATIBILITY_XML_ALDIKO = "aldiko";

    private static $db = NULL;

    public static function isMultipleDatabaseEnabled () {
        global $config;
        return is_array ($config['calibre_directory']);
    }
    
    public static function noDatabaseSelected () {
        return self::isMultipleDatabaseEnabled () && is_null (GetUrlParam (DB));
    }

    public static function getDbList () {
        global $config;
        if (self::isMultipleDatabaseEnabled ()) {
            return $config['calibre_directory'];
        } else {
            return array ("" => $config['calibre_directory']);
        }
    }
    
    public static function getDbNameList () {
        global $config;
        if (self::isMultipleDatabaseEnabled ()) {
            return array_keys ($config['calibre_directory']);
        } else {
            return array ("");
        }
    }

    public static function getDbName ($database = NULL) {
        global $config;
        if (self::isMultipleDatabaseEnabled ()) {
            if (is_null ($database)) $database = GetUrlParam (DB, 0);
            $array = array_keys ($config['calibre_directory']);
            return  $array[$database];
        }
        return "";
    }

    public static function getDbDirectory ($database = NULL) {
        global $config;
        if (self::isMultipleDatabaseEnabled ()) {
            if (is_null ($database)) $database = GetUrlParam (DB, 0);
            $array = array_values ($config['calibre_directory']);
            return  $array[$database];
        }
        return $config['calibre_directory'];
    }


    public static function getDbFileName ($database = NULL) {
        return self::getDbDirectory ($database) .'metadata.db';
    }

    private static function error () {
        if (php_sapi_name() != "cli") {
            header("location: checkconfig.php?err=1");
        }
        throw new Exception('Database not found.');
    }

    public static function getDb ($database = NULL) {
        if (is_null (self::$db)) {
            try {
                if (is_readable (self::getDbFileName ($database))) {
                    self::$db = new PDO('sqlite:'. self::getDbFileName ($database));
                } else {
                    self::error ();
                }
            } catch (Exception $e) {
                self::error ();
            }
        }
        return self::$db;
    }
    
    public static function checkDatabaseAvailability () {
        if (self::noDatabaseSelected ()) {
            for ($i = 0; $i < count (self::getDbList ()); $i++) {
                self::getDb ($i);
                self::clearDb ();
            }
        } else {
            self::getDb ();
        }
        return true;
    }

    public static function clearDb () {
        self::$db = NULL;
    }

    public static function executeQuery($query, $columns, $filter, $params, $n, $database = NULL, $numberPerPage = NULL) {
        $totalResult = -1;

        if (is_null ($numberPerPage)) {
            $numberPerPage = getCurrentOption ("max_item_per_page");
        }

        if ($numberPerPage != -1 && $n != -1)
        {
            // First check total number of results
            $result = self::getDb ($database)->prepare (str_format ($query, "count(*)", $filter));
            $result->execute ($params);
            $totalResult = $result->fetchColumn ();

            // Next modify the query and params
            $query .= " limit ?, ?";
            array_push ($params, ($n - 1) * $numberPerPage, $numberPerPage);
        }

        $result = self::getDb ($database)->prepare(str_format ($query, $columns, $filter));
        $result->execute ($params);
        return array ($totalResult, $result);
    }

}
