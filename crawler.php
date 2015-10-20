<?php

require_once("simple_html_dom.php");

abstract class request{
    public static function getPage($page){
        $html = file_get_html($page);
        if(!$html){
            return $html;
        }
        return $html->find("div[id=content]",0);
    }
}

class pageList{
    protected $pgcity;
    public static $state;
    public static $city;
    public static $filename;
    
    public function __construct($args) {
        if(count($args) !== 4){
            echo "Usage:\nphp ".$args[0]." city state filename\n";
            exit;
        }
        self::$city = $args[1];
        self::$state = $args[2];
        self::$filename = $args[3];
        $this->pgcity = str_replace(" ","-",self::$city);
        
    }
    public function main(){
        for($i = 1; $i < 3; $i++){
            if($i == 1){
                $html = request::getPage('https://kekanto.com.br/'.self::$state.'/'.$this->pgcity.'/top/restaurantes-delivery/');
            } else {
                $html = request::getPage('https://kekanto.com.br/'.self::$state.'/'.$this->pgcity.'/top/restaurantes-delivery/page:'.$i.'?&cat=36');
            }    
            $ul = $html->find('ul[id=bizes-content-list]',0);
            if($ul == null){
                break;
            }
            $this->parsePage($html);
        }
    }
    
    public function parsePage($html){
        $worker = 0;
        $tasks = array();
        foreach($html->find('ul[id=bizes-content-list]',0)->children() as $list){
            $tasks[++$worker] = new subPage($list);
        }
        foreach($tasks as $task){
            $task->join();
        }
    }


    public function hasPedidosJa($haystack){
        if(strpos($haystack, "pedidosja.com.br")){
            return "S";
        }
        return "N";
    }

    
}

class subPage extends Thread{
    protected $list;

    
    public function __construct($list) {
        $this->list = $list;
        $this->start();
    }
    
    public function run(){
        $list = $this->list;
        $anchor = $list->find('h2.biz-card-title a', 0);
        if($anchor == null){
            return;
        }
        $nome = str_replace(",",";",$anchor->plaintext);
        $href = $anchor->href;
        $page = request::getPage($href);
        if($page == false){
            $delivery = "N/A";
            $website = "N/A";
            $cep = "N/A";
        } else {
            $delivery = $this->hasDelivery($page);
            $website = $this->getWebsite($page);
            $cep = $this->getCEP($page);
        }
        $address = $list->find("div.biz-card-address span", 0);
        if($address == null){
            $address = "N/A"; 
        } else {
            $address = str_replace(",",";",trim($address->plaintext));
        }
        $address .= ",".pageList::$city.",".pageList::$state;
        $tel = $this->getPhone($list);
        $fp = fopen(pageList::$filename.".csv","a");;
        fwrite($fp,trim($nome).",".$address.",".$cep.",".trim($tel).",".$website.",".$delivery."\n");
        fclose($fp);
    }
    protected function hasDelivery($html){
        $deldiv = $html->find('div.kekantodelivery-info',0);
        return $deldiv == null ? "N":"S";
    }
    protected function getWebsite($html){
        $add = $html->find("div.biz-additional-info",0);
        if($add !== null){
            foreach($add->children() as $item){
                $strong = $item->find('strong',0);
                if($strong != null){
                    if($strong->plaintext != "Site oficial"){
                        continue;
                    }
                    $href = $item->find("a",0)->href;
                    return $href;
                }
            }
        }
        return "N/A";
    }
    protected function getCEP($html){
        $address = $html->find("address",0);
        if($address != null){
            foreach($address->find("a") as $anchor){
                $href = $anchor->href;
                if(strpos($href,"cep") !== false){
                    return $anchor->plaintext;
                }
            }
        }
        return "00000-000";
    }
    protected function getPhone($html){
        $phone = $this->list->find("div.biz-card-phone",0);
        return $phone == null ? "N/A":$phone->plaintext;
    }
}

$pages = new pageList($argv);
$pages->main();
