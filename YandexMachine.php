<?php
/**
 * by Novikov.ua 2016
 */


namespace nofikoff\yashagosha;

use app\controllers\SystemMessagesLogController;
use Yii;

use nofikoff\proxybrowser\ProxyBrowser;

class YandexMachine
{

    // режим XML или парсинга страниц яндекса через антигейи
    public $XMLmode = 1;

    public $gdomain = 'yandex.ru';
    public $glang = 'ru';
    public $gnearcity = '';

    // режим ТОЧНОЕ СООТВЕТСВИЕ в кавычкаъ
    public $exactly = false;
    // мобильная версия поисковика (прост как идея)
    public $mobile_version = false;
    //
    public $noJs = true;


    // ВАЖНО !!! тут без капчи можно иметь дело с яндексом с одного IP
    //XML yandex RU http://forum.sape.ru/showpost.php?p=1413632&postcount=4092
    //XML yandex RU http://forum.sape.ru/showpost.php?p=1413632&postcount=4092
    //XML yandex RU http://forum.sape.ru/showpost.php?p=1413632&postcount=4092


    public function get_page($strReq)
    {
        if ($this->XMLmode) {

        }

        $browser = new ProxyBrowser();
        return $browser->get_http($this->get_url_request($strReq));
    }

    private function get_url_request($strReq)
    {
        $strReq = trim($strReq);
        if ($this->exactly) {
            $strReq = '"' . $strReq . '"';
        }
        $strUrl = 'https://www.' . $this->gdomain . '/search?q=' . urlencode($strReq);
        if ($this->noJs) $strUrl .= '&gbv=1';
        return $strUrl;

    }

    // выводит грязный хтмл кэша конкретной страницы
    // и ее дату в массиве
    public function get_cache($url)
    {

    }
    /**
     * XML XML XML XML XML XML XML XML XML XML XML XML XML XML XML XML XML XML
     */

    // массив результата запроса в яндекс
    public function xml_get_search_result($strReq)
    {
        $url = 'https://yandex.com/search/xml?user=' . Yii::$app->get('settings')->get('system.yandex_API_user') . '&key=' . Yii::$app->get('settings')->get('system.yandex_API_key') . '&query=' . urlencode($strReq) . '&l10n=en&sortby=tm.order%3Dascending&filter=none&groupby=attr%3D%22%22.mode%3Dflat.groups-on-page%3D10.docs-in-group%3D1';
        $browser = new ProxyBrowser();
        $result = $browser->get_http($url);
        $result['result'] = $this->xml2array($result['raw']);
        return $result;
    }

    // выводит грязный хтмл кэша конкретной страницы
    // и ее дату в массиве
    public function xml_get_cache($url)
    {
        $url_no_www = ProxyBrowser::url_http_cutting(ProxyBrowser::url_www_cutting($url));
        $a = $this->xml_get_search_result('url:"' . $url_no_www . '" | url:"www.' . $url_no_www . '"');

        if (isset($a['result']['response']['results'])) {

            $result['result'] = 1;
            $result['error'] = $a['error']; // если вдруг будет
            // бляха муха нет даты возраста
            // TODO: Возраст кэша страницы по XML вытащить нельзя - оставим на перспективу дописать не XML функцию получения реального кэша Яндекса да и сдатой кэширования
            // даже этот параметр в яндексе мигающий
            //$result['update_content_date'] = date('Y-m-d', strtotime($a['result']['response']['results']['grouping']['group']['doc']['modtime']));
            $result['description'] = 'Возраст кэша страницы по XML вытащить нельзя';
            $result['code'] = 200;

        } else if (
            isset($a['result']['response']['error'])
            AND
            trim($a['result']['response']['error']) == 'Sorry, there are no results for this search'
        ) {
            // Аппаратого сбоя нет но ответ отрицательный
            // Аппаратого сбоя нет но ответ отрицательный
            // Аппаратого сбоя нет но ответ отрицательный
            $result['result'] = 0;
            $result['error'] = 0;
            $result['description'] = $a['result']['response']['error'];
            $result['code'] = 404;

        } else {
            // аппаратный сбой
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                3,
                "xml_get_cache",
                'При попытке получения XML ЯНдекс результата, непредвиденый сбой - немогу распарсить ответ яндекса'
            );
            $result['result'] = 0;
            $result['error'] = 1;
            $result['description'] = 'Аппаратный сбой';
            $result['code'] = 0;
        }


        return $result;

    }


    // впомогательная йункция
    private function xml2array($xml)
    {
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $this->xml2array_recurse($xml);
    }


    private function xml2array_recurse($xmlObject, $out = array())
    {
        if (sizeof((array)$xmlObject)) {
            foreach ((array)$xmlObject as $index => $node)
                $out[$index] = (is_object($node) || is_array($node)) ? $this->xml2array_recurse($node) : $node;
            return $out;
        } else {
            return (string)$xmlObject;
        }
    }

}
