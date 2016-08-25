<?php
/**
 * by Novikov.ua 2016
 */


namespace nofikoff\yashagosha;

//use Yii;

class GoogleMachine
{

    public $gdomain = 'google.ru';
    public $glang = 'ru';
    public $gnearcity = '';

    // режим ТОЧНОЕ СООТВЕТСВИЕ в кавычкаъ
    public $exactly = false;
    // мобильная версия поисковика (прост как идея)
    public $mobile_version = false;
    // мнять БЕСПОЛЕЗНО гугл выкупает
    public $noJs = true;



    public function get_page($strReq)
    {
        $browser= new Yii2Proxybrowser();
        return $browser->get_http($this->get_url_request($strReq));
    }


    private function get_url_request($strReq)
    {
        $strReq = trim($strReq);
        if ($this->exactly){
            $strReq = '"'.$strReq.'"';
        }
        $strUrl='https://www.'.$this->gdomain.'/search?q='. urlencode($strReq);
        if ($this->noJs) $strUrl.='&gbv=1';
        return $strUrl;

    }

}
