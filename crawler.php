<?php
/**
 * @link http://simplehtmldom.sourceforge.net/
 */
require_once("simple_html_dom.php");

abstract class request{
    /**
     * Return a simple_html_dom object or false
     * @param string $page the url of the page 
     * @return simple_html_dom|boolean
     */
    public static function getPage($page){
        $html = file_get_html($page);
        if(!$html){
            return $html;
        }
        return $html->find("div[id=content]",0);
    }
}

class pageList{
    /**
    * The name of the city that will be used on the url
    * @var string
    * @access protected
    */
    protected $pgcity;
    /**
    * The name of the State in Brazil (abbreviated)
    * @var string
    * @static
    */
    public static $state;
    /**
    * The name of the city
    * @var string
    * @static
    */
    public static $city;
    /**
    * The name of the file where the search results will be copied
    * @var string
    * @static
    */
    public static $filename;
    
    /**
     * Constructor initializes object properties
     * @param int $count ($argc)
     * @param array $args ($argv)
     */
    public function __construct($count, $args) {
        if($count !== 4){
            echo "Usage:\nphp ".$args[0]." city state filename\n";
            exit;
        }
        self::$city = $args[1];
        self::$state = $args[2];
        self::$filename = $args[3];
        $this->pgcity = str_replace(" ","-",self::$city);
        
    }
    /**
     * Start the pages requests
     */
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
    /**
     * Start and syncronize the threads
     * @param simple_html_dom $html
     */
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

    /**
     * Constructor initializes $list property and start the thread 
     * @param simple_html_dom $list
     */
    public function __construct($list) {
        $this->list = $list;
        $this->start();
    }
    
    /**
     * Run the thread and save the result in a file
     */
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
    
    /**
     * Check if html element exists
     * @param simple_html_dom $html
     * @access protected
     * @return string
     */
    protected function hasDelivery($html){
        $deldiv = $html->find('div.kekantodelivery-info',0);
        return $deldiv == null ? "N":"S";
    }
    
    /**
     * Check if the restaurant has a website and returns it
     * @param simple_html_dom $html
     * @access protected
     * @return string
     */
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
    
    /**
     * Check if the post code is available and returns it
     * @param simple_html_dom $html
     * @access protected
     * @return string
     */
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
    /**
     * Check if the telephone is available and returns it
     * @param simple_html_dom $html
     * @access protected
     * @return string
     */
    protected function getPhone($html){
        $phone = $this->list->find("div.biz-card-phone",0);
        return $phone == null ? "N/A":$phone->plaintext;
    }
}

$pages = new pageList($argc,$argv);
$pages->main();
