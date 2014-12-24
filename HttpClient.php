<?php
/**
 * Http客户端
 *
 * @author  QPWOEIRU96 <qpwoeiru96@gmail.com>
 */
class HttpClient {

    //curl handle
    private $_ch           = FALSE;

    //验证代理是否有效的地址
    private $_check_url    = 'http://so.meadin.com/ip.php';

    //代理地址
    private $_proxy_addr   = '';

    //代理端口
    private $_proxy_port   = 0;

    //代理用户
    private $_proxy_user   = '';

    //代理密码
    private $_proxy_passwd = '';

    //请求User-Agent
    private $_user_agent   = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22';

    //存储的cookie 文件
    private $_cookie_file  = '';

    //header Referer信息
    private $_referer      = NULL;

    //是否使用代理
    private $_use_proxy    = FALSE;

    //Cookie存储地址
    private $_cookie_storage_dir = 'cookie_storage';

    //是否使用的是HTTP代理通道
    private $_proxy_use_http_tunnel = TRUE;

    //连接超时时间
    private $_timeout = 5;

    /**
     * @param string $cookie_file Cookie文件名如果只是单一登录请设置为单一值
     */
    public function __construct($cookie_file = '') {

        $this->_init();

        $this->_initCookieStorage($cookie_file);
    }


    /**
     * 设置代理信息
     */
    public function setProxy($proxy_addr, $proxy_port, $is_http_tunnel = TRUE, $proxy_user = '', $proxy_passwd = '') {

        $this->_proxy_addr   = $proxy_addr;
        $this->_proxy_port   = $proxy_port;
        $this->_proxy_user   = $proxy_user;
        $this->_proxy_passwd = $proxy_passwd;
        $this->_proxy_use_http_tunnel = $is_http_tunnel;
        $this->_use_proxy    = TRUE;

    }

    
    public function __get($name) {

        switch($name) {
            case 'user_agent':
                return $this->_user_agent;
            case 'use_proxy':
                return $this->_use_proxy;
            case 'referer':
                return $this->_referer;
            case 'timeout':
                return $this->_timeout;
            default:
                return FALSE;
        }

    }

    public function __set($name, $value) {
        switch($name) {
            case 'user_agent':
                $this->_user_agent = $value;
                break;
            case 'use_proxy':
                $this->_use_proxy = $value;
                break;
            case 'referer':
                $this->_referer = $value;
                break;
            case 'timeout':
                $this->_timeout = $value;
                break;
            default:
                return FALSE;
        }
    }

    /**
     * 设置存储的CookieFile信息
     *
     */
    public function setCookieFile($file_path) {

        if(!file_exists($file_path)) return FALSE;

        $this->_cookie_file = $file_path;
        return TRUE;

    }

    public function get($url, $is_ajax = FALSE) {

        $this->_init();

        curl_setopt_array($this->_ch, array(
            CURLOPT_URL           => $url,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ));

        if($is_ajax) {
            curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array('X-Requested-With:XMLHttpRequest'));
        }

        $data = curl_exec($this->_ch);

        return $data;

    }

    final static function buildPostData($data) {

        $arr = array();
        foreach($data as $key => $val) {
            $arr[] = urlencode($key) . '=' . urlencode($val);
        }

        return implode('&', $arr);

    }

    //TODO:
    //application/x-www-form-urlencoded = 1
    //multipart/form-data = 2
    public function post($url, $data, $type = 1, $is_ajax = FALSE) {

        $this->_init();

        if($type === 1) {
            $data = self::buildPostData($data);
            curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array(
                'Content-length:' . strlen($data)
            ));
        }

        if($is_ajax) {
            curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array('X-Requested-With:XMLHttpRequest'));
        }

        curl_setopt_array($this->_ch, array(
            CURLOPT_URL           => $url,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST          => 1,
            CURLOPT_POSTFIELDS    => $data
        ));

        $data = curl_exec($this->_ch);

        return $data;

    }

    private function _init() {

        $this->_ch = curl_init();
        
        curl_setopt_array($this->_ch, array(
            CURLOPT_FOLLOWLOCATION  => 1,
            CURLOPT_RETURNTRANSFER  => 1,            
            CURLOPT_REFERER         => $this->_referer,
            CURLOPT_USERAGENT       => $this->_user_agent,
            CURLOPT_HEADER          => 0,
            CURLOPT_TIMEOUT            => $this->_timeout

        ));

        if($this->_use_proxy) {
            if( $this->_proxy_user !== '' ) {
                curl_setopt($this->_ch, CURLOPT_PROXYUSERPWD, $this->_proxy_user . ':' . $this->_proxy_passwd );
            }

            curl_setopt($this->_ch, CURLOPT_PROXY , $this->_proxy_addr . ':' . $this->_proxy_port);
            curl_setopt($this->_ch, CURLOPT_HTTPPROXYTUNNEL, $this->_proxy_use_http_tunnel);
        }                    

        if( $this->_cookie_file !== '') {
            curl_setopt_array($this->_ch, array(
                CURLOPT_COOKIEFILE => $this->_cookie_file,
                CURLOPT_COOKIEJAR  => $this->_cookie_file
            ));
        }
            

    }

    //
    public function check() {

        $data = $this->post($this->_check_url, array('test' => 'test'), 1);
        return $this->_use_proxy 
            ? ($data === $this->_proxy_addr) 
            : ($data === isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'] );

    }

    /**
     * 初始化 Cookie存储
     */
    private function _initCookieStorage ($cookie_file) {

        if($cookie_file == '') $file = base64_encode( time() . rand(1000, 9999) ) . '.txt';
        else $file = base64_encode($cookie_file) . '.txt';        

        $file_path = __DIR__ . DIRECTORY_SEPARATOR . $this->_cookie_storage_dir . DIRECTORY_SEPARATOR . $file;
        if(!file_exists($file_path)) touch($file_path);
        $this->_cookie_file = $file_path;
    }
}
