<?php
/**
 * Excel导出类
 *
 * @author  qpwoeiru96 <qpwoeiru96@gmail.com>
 * @date 2014年1月1日
 *
 * <example>
 * $exporter = new \MyExcel\Exporter(array(
 *     'tempDir' => __DIR__,
 *     'startRowIndex' => 3,
 * ));
 * 
 * $exporter->open(__DIR__ . '/template.xlsx');
 * $i = 1;
 * $data = array_map(function() use($i) { 
 *      return array(
 *          'id' => $i++,
 *          'title' => uniqid(),
 *          'date' => date('Y-m-d H:i:s', rand(0, 0x7fffffff)),
 *          'none' => '<>\'"&_+*(*&^*^%^&%'
 * );
 * }, range(0, 1000));
 *
 * $map = array(
 *      '1' => array('name' => 'id', 'type' => 'numeric'),
 *      '2' => array('name' => 'title', 'type' => 'string'),
 *      '3' => array('name' => 'date', 'type' => 'date'),
 *      '4' => array('name' => 'none', 'type' => 'string')
 * );
 *
 * $exporter->render($data, $map);
 * $exporter->save();
 * </example>
 */
namespace MyExcel;

class Exporter
{
    /**
     * 
     */    
    private $_zipArchive;

    protected $config;

    private $_sharedString;

    private $_defaultSheet;

    private $_file;

    //错误信息大全
    private $_errorMsgs = array(
        \ZipArchive::ER_EXISTS => 'File already exists.',
        \ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
        \ZipArchive::ER_INVAL  => 'Invalid argument.',
        \ZipArchive::ER_MEMORY => 'Malloc failure.',
        \ZipArchive::ER_NOENT  => 'No such file.',
        \ZipArchive::ER_NOZIP  => 'Not a zip archive.',
        \ZipArchive::ER_OPEN   => 'Can\'t open file.',
        \ZipArchive::ER_READ   => 'Read error.',
        \ZipArchive::ER_SEEK   => 'Seek error.',
    );

    public function __construct($config = array())
    {
        $this->config = array_merge(array(
            //临时文件目录
            'tempDir' => '/tmp/',
            //开始行索引
            'rowStartIndex' => 3,
        ), $config);
    }

    public function __get($name)
    {
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    /**
     * 打开xlsx模板文件
     *
     * @param  string $file xlsx文件
     * @throws \Exception
     * @return void
     */
    public function open($file)
    {
        if(!file_exists($file)) throw new \Exception($this->errorMsgs[\ZipArchive::ER_NOENT], \ZipArchive::ER_NOENT);
    
        $this->_file = $this->tempDir . DIRECTORY_SEPARATOR . self::genRandomFileName();
        copy($file, $this->_file);

        $this->_zipArchive = new \ZipArchive();
        $res = $this->_zipArchive->open($this->_file);

        if( $res !== true )
            throw new \Exception($this->errorMsgs[$res], $res);

        $this->_buildSharedString();
        $this->_buildDeafultSheet();
    }

    public function getFile()
    {
        return $this->_file;
    }

    public function getSharedString()
    {
        return $this->_sharedString;
    }

    public function getDefaultSheet()
    {
        return $this->_defaultSheet;
    }

    /**
     * 渲染数据
     * 
     * @param  array $data
     * @param  array $map  
     * @param  mixed $inject 注入函数在每个Row的时候
     * return 
     */
    public function render(array $data, array $map, $inject = null)
    {
        $this->_defaultSheet->insertData($data, $map, $inject);
    }

    public function save()
    {
        $sharedStringTempFile = $this->tempDir . DIRECTORY_SEPARATOR . self::genRandomFileName('.xml');
        $sheetSharedFile = $this->tempDir . DIRECTORY_SEPARATOR . self::genRandomFileName('.xml');
        $this->_sharedString->saveToFile($sharedStringTempFile);
        $this->_defaultSheet->saveToFile($sheetSharedFile);
        $this->_zipArchive->addFile($sheetSharedFile, 'xl/worksheets/sheet1.xml');
        $this->_zipArchive->addFile($sharedStringTempFile, 'xl/sharedStrings.xml');
        $this->_zipArchive->close();
        unlink($sheetSharedFile);
        unlink($sharedStringTempFile);


    }

    /**
     * 构造共享字符串
     */
    private function _buildSharedString()
    {
        $this->_sharedString = new SharedString($this->read('xl/sharedStrings.xml'));
    }

    /**
     * 
     */
    private function _buildDeafultSheet()
    {
        $this->_defaultSheet = new Sheet($this, $this->read('xl/worksheets/sheet1.xml'));
    }

    public static function genRandomFileName($ext = '.xlsx')
    {
        return md5(mt_rand(0, 0x7fffffff)) . $ext;
    }

    protected function read($innerFile)
    {
        return $this->_zipArchive->getFromName($innerFile);
    }
}


class SharedString
{
    const TEMPLATE = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"><R></R></sst>';

    private $_simpleXMLElement = null;

    private $_items = array();

    public $count;

    public $uniqueCount;

    public function __construct($xml = '')
    {

        if(trim($xml) === '') {
            $this->_simpleXMLElement = $this->_createXML();
        } else {
            $this->_simpleXMLElement = simplexml_load_string($xml);
            $this->_parseItem();
        }
    }

    public function pushItem($item)
    {
        $this->_items[] =  $item;
        $this->count += 1;
        $this->uniqueCount += 1;
        return $this->count - 1;
    }

    private function _parseItem()
    {
       
        foreach($this->_simpleXMLElement->si as $name => $child) {
            $this->_items[] = (string)$child->t;
        }
        unset($this->_simpleXMLElement->si);
        $this->count = $this->_simpleXMLElement['count'];
        $this->uniqueCount = $this->_simpleXMLElement['uniqueCount'];
        $this->_simpleXMLElement->R = '';
    }

    public function saveToFile($filename)
    {
        $this->_simpleXMLElement['count'] = $this->count;
        $this->_simpleXMLElement['uniqueCount'] = $this->uniqueCount;
        $text = $this->_simpleXMLElement->asXML();
        $pos = stripos($text, '<R></R>');

        file_put_contents($filename, substr($text, 0, $pos));


        $temp = '';
        foreach($this->_items as $key => $item) {
			// id="' . $key . '"
            $item =  '<si><t xml:space="preserve">' . htmlspecialchars($item) . '</t></si>';
            $temp .= $item;

        }
        file_put_contents($filename, $temp, FILE_APPEND);
        unset($temp);
        file_put_contents($filename, substr($text, $pos + 7), FILE_APPEND);
        
    }

    public function __toString()
    {
        $text = $this->_simpleXMLElement->asXML();
        $pos = stripos($text, '<R></R>');
        return substr($text, 0, $pos) . implode('', $this->_items) . substr($text, $pos + 7);
    }

    /**
     * 如果不存在那么创建此XML
     *
     * @return \SimpleXMLElement
     */
    private function _createXML()
    {
        return simplexml_load_string(self::TEMPLATE);
    }
}


class Sheet
{
    private $_simpleXMLElement;

    private $_sharedString;

    private $_rows = array();

    private $_exporter;

    private $_maxRowIndex;

    public function __construct(Exporter $exporter, $xml)
    {
        $this->_exporter         = $exporter;
        $this->_sharedString     = $exporter->getSharedString();
        $this->_simpleXMLElement = simplexml_load_string($xml);
        $this->_parseRow();

    }

    public function insertData($data, $map, $inject = null)
    {
        $rowIndex       = $this->_exporter->rowStartIndex;
        $temp           = array_keys($map);
        //$maxColumnIndex = max($temp);
        //$minColumnIndex = min($temp);

        foreach($data as $row) {

            if($inject !== null)
                $row = $inject($row);

            $xml = "<row r=\"{$rowIndex}\">";

            foreach($map as $column => $config) {

                $column = self::baseConvert($column);
                $value = $row[$config['name']];

                if((string)$value === '') continue;

                //if($config['type'] === 'date') {
                    //注意时区 很容易出现Bug 需要Styles的支持 暂时放弃支持
                    //(25569.333333333333333 + (strtotime($value) / 86400))
                    //$xml .= "<c r=\"{$column}{$rowIndex}\" t=\"d\"><v>" . date('Y-m-d H:i:s', strtotime($value))  . "</v></c>";
                //} else
                if($config['type'] === 'numeric') {
                    $xml .= "<c r=\"{$column}{$rowIndex}\"><v>{$value}</v></c>";
                } else {
                    $si = $this->_sharedString->pushItem($value);
                    $xml .= "<c r=\"{$column}{$rowIndex}\" t=\"s\"><v>{$si}</v></c>";
                }
            }

            $xml .= "</row>";
            $this->_rows[$rowIndex] = $xml;

            ++$rowIndex;
        }
    }

    public function saveToFile($filename)
    {
        $text = $this->_simpleXMLElement->asXML();
        $pos = stripos($text, '<R></R>');
        file_put_contents($filename, substr($text, 0, $pos));
        file_put_contents($filename, implode('', $this->_rows), FILE_APPEND);
        file_put_contents($filename, substr($text, $pos + 7), FILE_APPEND);

    }


    /**
     *  进制转换
     *  @todo
     */
    public static function baseConvert($num)
    {
        $str = " ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        return $str{$num};
    }

    public function getSharedString()
    {
        return $this->_sharedString;
    }

    private function _parseRow()
    {
        foreach($this->_simpleXMLElement->sheetData->row as $name => $child) {
            $this->_rows[(int)$child['r']] = $child->asXML();
        }
        unset($this->_simpleXMLElement->sheetData->row);
        $this->_simpleXMLElement->sheetData->R = '';
    }

    public function __toString()
    {
        return $this->_simpleXMLElement->asXML();
    }


}

