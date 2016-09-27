<?php
/**
 * by Novikov.ua 2016
 */


namespace nofikoff\yashagosha;

//use Yii;

use app\controllers\SystemMessagesLogController;
use nofikoff\proxybrowser\ProxyBrowser;

class GoogleMachine
{

    public $gdomain = 'google.ru';
    public $glang = 'ru';
    public $gnearcity = '';
    public $debug = false;

    // режим ТОЧНОЕ СООТВЕТСВИЕ в кавычкаъ
    public $exactly = false;
    // мобильная версия поисковика (прост как идея)
    public $mobile_version = false;
    // мнять БЕСПОЛЕЗНО гугл выкупает
    public $noJs = true;

    // максимальная длина запроса в словах
    public $query_max_length = 30;

    // задержка между обращениями в ГУгл
    public $timeout_sec = 1; // обычно 3, но есл ииспоьлзовать тор прокси, то и так тупит ответка


    // читаем результат запроса в поисковике
    public function get_page($strReq)
    {

        $result['error'] = false;
        $result['description'] = '';

        if (!trim($strReq)) {
            $result['error'] = true;
            $result['description'] = 'ERROR. Пустаой запрос';
            return $result;
        }

        $browser = new ProxyBrowser();
        $browser->erase_js = true;
        $browser->timeout = $this->timeout_sec;
        $browser->interface_lang = 'en';
        $a = $browser->get_http($this->get_url_request($strReq));
        if (!$a['error']) $result['result'] = $a['result'];
        else echo $a['description'];

        if ($this->debug OR 1) {
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                1,
                "result",
                "Просто накапливаем выдачу поисковиков Гугл ",
                $result['result']
            );
        }

        if (preg_match('/To continue, please type the characters below/i', $result['result'])) {
            $result['error'] = true;
            $result['description'] = 'ERROR. Каптча в выдаче';
        }

        //
        return $result;

    }

    // читаем результат запроса в поисковике
    public function get_cache($url)
    {

        $url = ProxyBrowser::url_http_cutting($url);

        $result['error'] = false;
        $result['description'] = '';
        $result['date'] = '000-00-00';
        $result['result'] = '';

        if (!trim($url)) {
            $result['error'] = true;
            $result['description'] = 'ERROR. Пустаой запрос';
            return $result;
        }

        $browser = new ProxyBrowser();
        $browser->erase_js = true;
        $browser->timeout = $this->timeout_sec;
        $browser->interface_lang = 'en';
        $a = $browser->get_http('http://webcache.googleusercontent.com/search?q=cache:' . $url);
        if (!$a['error'])
            $result['result'] = $a['result'];
        else
            echo 'GM error get http: '.$a['description'];


        if ($this->debug OR 1) {
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                1,
                "Google get_cache",
                "Просто накапливаем выдачу поисковиков Гугл ",
                $result['result']
            );
        }


        if (preg_match('/was not found on this server\.\s+<ins>That’s all we know\.<\/ins>/i', $result['result'])) {
            $result['error'] = true;
            $result['description'] = 'ERROR. No cache';
        } else if (!preg_match('/page as it appeared on (.*?)\./i', $result['result'], $d)) {
            $result['error'] = true;
            $result['description'] = 'ERROR. Не вижу даты кэша - значит кэшбитый';
        } else {
            $result['date'] = date('Y-m-d', strtotime($d[1]));
            // дату определили
            // выкидывем первую строку с вставкой гугл
            $result['result'] = preg_replace('/^.+\n/', '', $result['result']);
            $result['result'] = preg_replace('/^.+\n/', '', $result['result']);

        }

        if (preg_match('/To continue, please type the characters below/i', $result['result'])) {
            $result['error'] = true;
            $result['description'] = 'ERROR. Каптча в выдаче';
        }


        //
        return $result;

    }


    // формирует адресную строку гугла с нужными апарметрами и доменом
    public function get_url_request($strReq)
    {
        $strReq = trim($strReq);
        if ($this->exactly) {
            $strReq = '"' . $strReq . '"';
        }
        $strUrl = 'https://www.' . $this->gdomain . '/search?q=' . urlencode($strReq);
        if ($this->noJs) $strUrl .= '&gbv=1';
        return $strUrl;

    }


    // парсим количество найденых страниц из выдач гугла
    public function parse_number_finded_results($strResponce)
    {
        $result['error'] = false;
        $result['description'] = '';
        $result['result'] = '';

        if (preg_match('/did not match any documents/i', $strResponce))
            $result['result'] = 0;

        else if (preg_match('/No results found for/', $strResponce))
            $result['result'] = 0;

        else if (preg_match('/>About ([0-9]+) results</', $strResponce, $d))
            $result['result'] = $d[1];

        else if (preg_match('/>([0-9]+) results</', $strResponce, $d))
            $result['result'] = $d[1];

        else if (preg_match('/>([0-9]+) result</', $strResponce, $d))
            $result['result'] = $d[1];

        else {
            $result['error'] = true;
            $result['description'] = 'ERROR Парсинг страниы Не могу распарсить страницу гугла на предмет количества резульататов';
        }
        return $result;
    }


}
