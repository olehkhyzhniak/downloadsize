<?php 
if(!isset($argv[0])) {
    fwrite(STDERR, 'argc and argv are disabled' . PHP_EOL);
}

$url = '';
$affectedResourceType = '';

if (!empty($argv[1])) {
    $url = $argv[1];
}
if (!empty($argv[2])) {
    $affectedResourceType = $argv[2];
}

$estimator = new UrlEstimator($url, $affectedResourceType);
$result = $estimator->run();

/**
 * URL resource size estimator class
 * @author Oleh Khyzhniak
 */
class UrlEstimator
{    
    private $url = '';
    private $affectedResourceType = '';
    private $host = '';
    private $scheme = '';
    private $totalSize = 0;
    private $totalCount = 0;
    
    /**
     * Class constructor
     * 
     * @param string $url
     * @param string $affectedResourceType
     * @return void
     */
    public function __construct($url, $affectedResourceType)
    {
        $this->url = $url;
        $this->affectedResourceType = $affectedResourceType;
        $this->host = parse_url($url, PHP_URL_HOST);
        $this->scheme = parse_url($url, PHP_URL_SCHEME);
    }
    
    /**
     * Run URL processing    
     * 
     * @return void Directs output to the console
     */    
    public function run()
    {
        if (empty($this->url)) {
            $this->consoleWrite('Please specify the URL.');
            exit();
        }
        /*
         * get all the links on the page
         */
        $urlContent = file_get_contents($this->url);
        if ($urlContent === false) {
            $this->consoleWrite('This site can\'t be reached.');
            exit();
        }
        $this->totalSize += strlen($urlContent);
                
        $host = parse_url($url, PHP_URL_HOST);

        preg_match_all('/<link.*href="(.*)".*\/?>/i',$urlContent, $metaLinks);        
        $this->processLinks($metaLinks[1]);
      
        preg_match_all('/src\s*=\s*[\'"]([^\'"]+)[\'"]/i', $urlContent, $media); 
        $this->processLinks($media[1]);
        
        $this->consoleWrite('Full size of page and embedded resourses: ' . number_format($this->totalSize/1024, 2). 'kb');
        $this->consoleWrite('Total external links number: ' . $this->totalCount);        
    }
    
    /**
     * Iterates the given resource links and calculate their size
     * 
     * @param array $linksContainer
     * @return void Directs output to the console
     */ 
    private function processLinks($linksContainer)
    { 
        foreach ($linksContainer as $key => $resourceURL) {
             
            if (empty($resourceURL)) {
                continue;
            }
            
            if (strpos($resourceURL, 'http://' ) === false && strpos($resourceURL, 'https://' ) === false) {
                /**
                 * suppose these this links are relative
                 */
                $resourceURL = ltrim($resourceURL, '/');                
                $resourceURL = $this->scheme . '://'. $this->host . '/' . $resourceURL;                              
            }
            
            if ($this->validateURL($resourceURL) == false) {
                continue;
            }
        
            [$resourceSize, $contentType] = $this->getSize($resourceURL);
            if (
                $resourceSize >= 0
                && ($this->affectedResourceType == '' || ($this->affectedResourceType != '' && $contentType == $this->affectedResourceType))
            ) {
                $this->totalCount += 1;
                $this->totalSize += $resourceSize;
                $this->consoleWrite('URL: ' . $resourceURL . ' Size=' . $resourceSize . ' bytes');
                flush();
            }            
        }
    }
    
    /**
     * Makes HEAD CURL request on given resource URL  
     * 
     * @param string $resourceURL
     * @return array Container with two elements $resourceSize and $contentType
     */ 
    private function getSize($resourceURL)
    {
        $pointer = curl_init($resourceURL);
        curl_setopt($pointer, CURLOPT_NOBODY, 1);
        curl_setopt($pointer, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($pointer, CURLOPT_HEADER, 0);
        curl_setopt($pointer, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($pointer, CURLOPT_MAXREDIRS, 3);
        curl_setopt($pointer, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36');
        curl_exec($pointer);
        
        $resourceSize = curl_getinfo($pointer, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentType = '';
        if ($this->affectedResourceType !== '') {
            $contentTypeRaw = curl_getinfo($pointer, CURLINFO_CONTENT_TYPE);
            $contentType = $this->getType($contentTypeRaw);
        }
                
        curl_close($pointer); 
        
        return [$resourceSize, $contentType];
    }
    
    /**
     * Checks if given URL is valid   
     * 
     * @param string $url
     * @return bool "true" on valid, "false" otherwise
     */  
    private function validateURL($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $encoded_path = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encoded_path), $url);

        return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
    }
    
    /**
     * Generate url type based on raw content type data.    
     * 
     * @param string $contentTypeRaw
     * @return string $urlType Valid return types are: images, documents, media, other
     */  
    private function getType($contentTypeRaw)
    {
        $urlType = 'other'; 
        /**
         * Simple comparison that shows the only general idea.
         * Here should be a strict match with all the standard MIME types.
         */
        if (strpos($contentTypeRaw, 'image') !== false) {
            $urlType = 'images';
        } else if (strpos($contentTypeRaw, 'application') !== false || strpos($contentTypeRaw, 'text')) {
            $urlType = 'documents';
        } else if (strpos($contentTypeRaw, 'audio') !== false || strpos($contentTypeRaw, 'video')) {
            $urlType = 'media';
        }
        return $urlType;
    }
    
    /**
     * Sends output to console    
     * 
     * @param string $message
     * @return void Sends output to console
     */  
    private function consoleWrite($message)
    {
        echo $message . PHP_EOL;
    }    
}
