<?php
namespace DocumentConverter;

/**
 * 
 */
interface ConvertHandlerInterface
{
    /**
     * 是否需要文字信息
     * 
     * @return bool
     */
    public function needText();

    /**
     * 是否需要略缩图
     * 
     * @return bool
     */
    public function needThumb();

    /**
     * 对于错误如何处理
     * 此处的错误代表无法执行下去了
     * 
     * @param  int $code
     * @param  string $message
     * @return void
     */
    public function error($code, $message);

    /**
     * 保存PDF文件
     * 
     * @param  string $file
     * @return void
     */
    public function savePDF($file);

    /**
     * 保存SWF文件
     * 
     * @param  string $file
     * @return void
     */
    public function saveSWF($file);

    /**
     * 保存文本内容
     * 
     * @param  string $text
     * @return void
     */
    public function saveText($text);

    /**
     * 转换之后执行的
     * 
     * @return void
     */
    public function afterConvert();

    /**
     * 转换之前执行的
     * 
     * @return void
     */
    public function beforeConvert();
}

class Converter
{
    const DEBUG = true;

    public $config;

    public $handler;

    protected $mimeType = array(
        '.txt'  => 'text/plain',
        '.doc'  => 'application/msword',
        '.docx' => 'application/msword',
        '.ppt'  => 'application/powerpoint',
        '.pptx' => 'application/powerpoint',
        '.xls'  => 'application/msexcel',
        '.xlsx' => 'application/msexcel',
        '.pdf'  => 'application/pdf'
    );

    public function __construct(array $config = array())
    {
        $this->config = (object)array_merge(array(

            //office2pdf的执行文件 用于转换office文档到pdf
            'sofficeBin'    => 'soffice.bin',
            
            //pdf2swf的执行文件 用于转换pdf到swf
            'pdf2swfBin'    => '/usr/local/bin/pdf2swf',
            
            //ImageMagick convert的执行文件 用于提取pdf的略缩图 @deprecated
            'convertBin'    => 'convert',

            // ghost script bin
            'gsBin'         => 'gs',

            //实际进行转换的命令行
            'unoconvBin'    => 'unoconv',
            
            //pdftotext 的执行文件 用于提取文件的概要
            'pdftotextBin'  => 'pdftotext',
            
            //临时文件夹的路径 默认为/tmp
            'tempDir'       => '/tmp',

            //转换swf的最大时间
            'maxConvertSWFTime' => 120,

            //略缩图的高度
            'thumbHeight'   => 800,
            
            //略缩图的宽度
            'thumbWidth'    => 600,
            
            //略缩图位图深度 @deprecated
            'thumbPNGDepth' => 16,
            
            //生成的略缩图质量 @deprecated
            'thumbQuality'  => 60,
            
            //略缩图的文件后缀 代表了文件类型 @deprecated
            'thumbExt'      => '.jpg',
            
            //略缩图其他参数 @deprecated
            //array('set'   => 'colorspace RGB', 'colorspace' => 'gray') 使用如此可使图片变灰
            'thumbArgs'     => array()

        ), $config);
    }

    /**
     * 
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function convert($file)
    {
        //转换之前执行的动作
        $this->handler->beforeConvert();

        //原始的文件扩展名称
        $oriExt   = '.' . strtolower(pathinfo($file, PATHINFO_EXTENSION));

        //获取文件的mime
        $mime = isset($this->mimeType[$oriExt]) ? $this->mimeType[$oriExt] : '';
        if($mime === '')
            throw new \Exception('file type no support');

        $type = explode('/', $mime);
        $type = reset($type);

        //拷贝一份到临时空间进行转换
        $docFile  = $this->config->tempDir . DIRECTORY_SEPARATOR . self::getRandomName($oriExt);

        @copy($file, $docFile);

        if(!file_exists($docFile))
            throw new \Exception('copy document failed!');

        //如果是文本文档 那么需转换成UTF-8
        if($type === 'text') {
            $textContent = file_get_contents($docFile);
            //判定编码
            $encoding = mb_detect_encoding($textContent, array('ascii', 'gb2312', 'gbk', 'utf-8'));
            if('utf-8' !== strtolower($encoding)) {
                $textContent = mb_convert_encoding($textContent, 'utf-8', $encoding);
                file_put_contents($docFile, $textContent);
            }         
        }

        $pdfFile = str_replace($oriExt, '.pdf', $docFile);

        //开始转换pdf
        if($oriExt !== '.pdf') {
            $pdfCommand = $this->buildPDFCommand($docFile);
            exec($pdfCommand, $output, $return);
            $output = implode("\n", (array)$output);
            @unlink($docFile);
            if(!file_exists($pdfFile)) {
                throw new \Exception('pdf generate failed. console error message: ' . $output);
            }
        }

        //保存PDF文件
        $this->handler->savePDF($pdfFile);

        //开始获取文字信息
        if($this->handler->needText()) {

            if($type === 'text') {

                $this->handler->saveText($textContent);
            } else {

                $txtFile    = str_replace($oriExt, '.txt', $docFile);
                $txtCommand = $this->buildTXTCommand($pdfFile);
                

                //保存文字信息 如果输出失败 并不会报错 非致命性错误
                if(file_exists($txtFile)) {
                    $this->handler->saveText(file_get_contents($txtFile));
                    @unlink($txtFile);
                } else {
                    $this->handler->saveText('');
                }
            }
        }

        //开始获取略缩图
        if($this->handler->needThumb()) {
            $thumbFile      = str_replace($oriExt, $this->config->thumbExt, $docFile);
            $thumbCommand = $this->buildThumbCommand($pdfFile, $thumbFile);
            exec($thumbCommand, $output, $return);
            $output       = implode("\n", (array)$output);
            
            if(file_exists($thumbFile)) {
                $this->handler->saveThumb($thumbFile);
                @unlink($thumbFile);
            } else {
                @unlink($docFile);
                @unlink($pdfFile);
                throw new \Exception('thumb generate failed!');
            }
        }

        $swfFile    = str_replace($oriExt, '.swf', $docFile);
        $swfCommand = $this->buildSWFCommand($pdfFile, $swfFile);
        exec($swfCommand, $output, $return);
        $output     = implode("\n", (array)$output);

        if(!file_exists($swfFile)) {
        
            if( stripos($output, 'too complex to render') !== false ) {
            
                $swfCommand = $this->buildSWFCommand($pdfFile, $swfFile, true);
                exec($swfCommand, $output, $return);
                $output = implode("\n", (array)$output);
                
                if(!file_exists($swfFile)) {
                    @unlink($pdfFile);
                    @unlink($docFile);
                    throw new \Exception('swf generate failed. console error message: ' . $output);
                }
            } elseif (stripos($output, 'disallows copying') !== false) {
            
                $output = '文档已加密无法进行转换.';
                @unlink($pdfFile);
                @unlink($docFile);
                throw new \Exception('swf generate failed. console error message: ' . $output);
            } else {
                throw new \Exception('swf generate failed. console error message: ' . $output);
            }
        }

        $this->handler->saveSWF($swfFile);
        @unlink($swfFile);
        @unlink($pdfFile);

        $this->handler->afterConvert();
            
    }

    /**
     * 
     * @param   $command 
     * @return string
     */
    public function exec($command)
    {

        exec($txtCommand, $output, $return);
        return implode("\n", (array)$output);
    }


    public function buildPDFCommand($filePath)
    {
        //如果unoconvBin不为空 那么使用unoconv 否则使用sofficeBin
        if(!empty($this->config->unoconvBin)) {
            return implode(' ', array(
                $this->config->unoconvBin,
                '--format pdf',
                '"' . escapeshellcmd($filePath) . '"',
            ));
        } else {
            return implode(' ', array(
                $this->config->sofficeBin,
                '--nologo',
                '--headless',
                '--invisible',
                '--norestore',
                '--convert-to pdf',
                '--outdir "' . escapeshellcmd($this->config->tempDir) . '"',
                '"' . escapeshellcmd($filePath) . '"',
                '2>&1'
            ));
        }
    }

    public function buildTXTCommand($filePath)
    {
        return implode(' ', array(
            $this->config->pdftotextBin,
            $filePath
        )); 
    }

    public function buildThumbCommand($filePath, $target)
    {

        return implode(' ', array(
            $this->config->gsBin,
            "-o \"{$target}\"",
            "-sDEVICE=pngalpha",
            "-dLastPage=1",
            "-r72",
            "-dDEVICEWIDTHPOINTS=" . (int)$this->config->thumbWidth,
            "-dDEVICEHEIGHTPOINTS=" . (int)$this->config->thumbHeight,
            '"' . $filePath . '"',
            '2>&1'
        ));   

        //以下的废弃了 使用ghost script 更快速点
        $args = array();
        foreach($this->config->thumbArgs as $name => $arg) {
            $args[] = escapeshellcmd('-' . $name) . ' ' . escapeshellcmd($arg);
        }

        return implode(' ', array(
            $this->config->convertBin,
            '"' . $filePath . '[0]' . '"',
            '-resize ' . (int)$this->config->thumbWidth . 'x' . (int)$this->config->thumbHeight,
            '-define png:depth=' . (int)$this->config->thumbPNGDepth,
            '-quality ' . (int)$this->config->thumbQuality,
            implode(' ', $args),
            $target,
            '2>&1'
        ));
    }

    public function buildSWFCommand($filePath, $target, $usePoly2bitmap = false)
    {
        return implode(' ', array(
            $this->config->pdf2swfBin,
            '"' . escapeshellcmd($filePath) . '"',
            '--flashversion 9',
            '--jpegquality 80',
            '--set enablezlib',//bitmap
            $usePoly2bitmap ? '--set poly2bitmap' : '',
            '-qq',
            '--set languagedir=' . __DIR__ . '/languages/chinese-simplified',///usr/local/share/xpdf
            '--set languagedir=' . __DIR__ . '/languages/chinese-traditional',///usr/local/share/xpdf
            '--maxtime ' . (int) $this->config->maxConvertSWFTime,
            '--fontdir ' . __DIR__ . DIRECTORY_SEPARATOR . 'fonts',
            '--output "' . escapeshellcmd($target) . '"',
            '2>&1'
        ));
    }

    public static function getRandomName($ext = '.tmp')
    {
        return md5(rand(0, 0x7fffffff) . microtime(true)) . $ext;
    }

}
