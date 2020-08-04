<?php
namespace Component\Webidas;
use Request;
use Exception;
/**
 * Webidas Debugger Class
 * @package Component\Webidas
 * @usage for godo5
 * @charset utf-8
 */
class Webidas {

    public static $elapsed;
    public static $unique = array('203.173.98.30');
    public static $inspect = array(
        '202.8.191.181',
        '221.163.222.218',
        '210.216.153.112'
    );
    public static $scheduleDt = '2020-07-21 09:00:00';
    public static $deployDt = '2020-07-20 20:00:00';
    public static $operation = true;

    public static function on() {
        if(self::$operation === true) {
            return true;
        }
        return false;
    }


    public static function isStrict() {
        return self::isAccessible();
    }

    /**
     * 오픈 로드맵 적용
     * @param bool $timeCheck
     * @return bool
     */
    public static function isInspect($timeCheck = false) {
        if($timeCheck == true) {
            $scheduleDt = new \DateTime(self::$scheduleDt);
            $scheduleTimestamp= $scheduleDt->getTimestamp();
            if($scheduleTimestamp < time()) {
                return true;
            } else {
                return self::roadMap('CBT');
            }
        } else  {
            return self::roadMap('CBT');
        }
    }

    public static function isScheduleDate() {
        $scheduleDt = new \DateTime(self::$scheduleDt);
        $scheduleTimestamp= $scheduleDt->getTimestamp();
        if($scheduleTimestamp < time()) {
            return true;
        }
        return false;
    }

    public static function isDeployDate() {
        $deployDt = new \DateTime(self::$deployDt);
        $deployTimestamp= $deployDt->getTimestamp();
        if($deployTimestamp < time()) {
            return true;
        }
        return false;
    }

    /**
     * IP Check function
     * @param string $mode FREE|STRICT
     * @param array|null $numbers
     * @return boolean
     */
    public static function isAccessible($mode = 'STRICT', $numbers = null)  {
        if(is_null($mode)) $mode = 'STRICT';
        if(is_null($numbers)) $numbers =  self::$unique;
        if($mode == 'FREE') {
            return true;
        }else {
            if (in_array(Request::getRemoteAddress(),$numbers)) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function setException($data) {

        throw new Exception($data);

    }

    public static function roadMap($step, $address = []) {

        $step = strtolower($step);

        switch($step) {
            case 'prototype':
                return self::isAccessible();
            case 'cbt':
                if(empty($address)==true) {
                    if(empty(self::$inspect)==true) {
                        return self::isAccessible();
                    } else {
                        $address = array_merge(self::$unique, self::$inspect);
                        return self::isAccessible('STRICT', $address);
                    }
                } else {
                    $address = array_merge(self::$unique, $address);
                    return self::isAccessible('STRICT', $address);
                }
            case 'obt':
            case 'open':
                return true;
        }

    }


    public static function _parse_variable($variable, $depth=10,$i=0,&$objects = array())  {
        $string = "";
        $search = array("\0", "\a", "\b", "\f", "\n", "\r", "\t", "\v");
        $replace = array('\0', '\a', '\b', '\f', '\n', '\r', '\t', '\v');
        switch (gettype($variable)) {
            case 'boolean':
                $string .= $variable ? 'true' : 'false';
                break;
            case 'integer':
                $string .= $variable;
                break;
            case 'double':
                $string .= $variable;
                break;
            case 'resource':
                $string .= '[resource]';
                break;
            case 'NULL':
                $string .= "null";
                break;
            case 'unknown type':
                $string .= 'unknown';
                break;
            case 'string':
                /*$len = strlen($variable);
                $variable = str_replace($search, $replace, substr($variable, 0, 100), $count);
                $variable = substr($variable, 0, 100);
                if ($len < 100) $string .= '"' . $variable . '"';
                else $string .= 'string(' . $len . '): "' . $variable . '"...';*/
                $string = $variable;
                break;
            case 'array':
                $len = count($variable);
                if ($i == $depth) $string .= 'array(' . $len . ') {...}';
                elseif (!$len) $string .= 'array(0) {}';
                else {
                    $keys = array_keys($variable);
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= "array($len)\n" . $spaces . '{';
                    $count = 0;
                    foreach ($keys as $key) {
                        if ($count == 255) {
                            $string .= "\n" . $spaces . "  ...";
                            break;
                        }
                        $string .= "\n" . $spaces . "  [$key] => ";
                        $string .= self::_parse_variable($variable[$key], $depth, $i + 1, $objects);
                        $count++;
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
            case 'object':
                $id = array_search($variable, $objects, true);
                if ($id !== false)
                    $string .= get_class($variable) . '#' . ($id + 1) . ' {...}';
                else if ($i == $depth)
                    $string .= get_class($variable) . ' {...}';
                else {
                    $id = array_push($objects, $variable);
                    $array = (array)$variable;
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= get_class($variable) . "#$id\n" . $spaces . '{';
                    $properties = array_keys($array);
                    foreach ($properties as $property) {
                        $name = str_replace("\0", ':', trim($property));
                        $string .= "\n" . $spaces . "  [$name] => ";
                        $string .= self::_parse_variable($array[$property], $depth, $i + 1, $objects);
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
        }
        return $string;
    }


    public static function dumper() {
        if(self::isAccessible()) {
            echo "<xmp style='height:200px!important;overflow-Y:scroll;overflow-X:hidden;background: #1B2B34;color:#93C08F'>";
            $arguments=func_get_args();
            $message = '';
            echo ' URL : '.\Request::server()->get('REQUEST_URI') . " : This View is Only For Developer By ".\Request::server()->get('REMOTE_ADDR')."\n";
            $callerInfo = self::caller();

            //var_dump($callerInfo);

            echo 'FILE : '.$callerInfo['file']."\n";
            echo 'LINE :'. $callerInfo['line']."\n";
            if(!empty($callerInfo['class'])) {
                echo 'CLASS : ' . $callerInfo['class'] . "\n";
            }
            echo 'FUNCTION : '.$callerInfo['function']."\n";

            echo "[start]________________________________________________________\n";
            if(func_get_args() > 0) {
                foreach ($arguments as $index=>$argument) {
                    $message .=self::_parse_variable($argument)."\n";
                }
            }
            echo $message;
            //echo $tpl_file;
            echo "\n[end]________________________________________________________\n";
            echo "</xmp>";
        }
    }

    public static function _dumper() {
        if(self::isAccessible()) {
            $arguments=func_get_args();
            $message = '';
            echo "[_dumper_start]________________________________________________________\n";
            if($arguments > 0) {
                foreach ($arguments as $index=>$argument) {
                    $message .=self::_parse_variable($argument)."\n";
                }
            }
            echo $message;
            //echo $tpl_file;
            echo "\n[_dumper_end]________________________________________________________\n";
        }
    }


    public static function stop() {
        if(self::isAccessible()) {
            die;
        }
    }

    /**
     * get last call file info
     * @return array
     */
    public static function caller() {
        $backtrace= debug_backtrace(0);
        //var_dump($backtrace);
        //Webidas::stop();
        //return $backtrace;
        //self::_dumper($backtrace);
        //self::stop();
        //$maxTrace = count($backtrace);
        $breakpoint = false;
        $return = null;
        foreach($backtrace as $trace) {

            if($trace['class']=='Component\Webidas\Webidas' && $trace['function'] == 'caller') {
                continue;
            } else if($trace['class']=='Component\Webidas\Webidas' && $trace['function'] == 'dumper') {
                $breakpoint = true;
                $return['file'] = $trace['file'];
                $return['line'] = $trace['line'];
            } else {
                if($breakpoint) {
                    $return['class'] = $trace['class'];
                    $return['function'] = $trace['function'];
                } else {
                    $return = $trace;
                }
                break;
            }
        }

        /*if($backtrace[$maxTrace-2]['class'] == 'Webidus' || $backtrace[0]['class'] == 'Webidus') {
            return $backtrace[1];
        } else {
            return $backtrace[$maxTrace-2];
        }*/
        return $return;
    }


    /**
     * 현재 파일에 인클루드된 파일 목록
     */
    public static function include_list() {
        self::dumper(get_included_files());
    }

    /**
     * 디파인된 모든 상수와 그 값의 연관 배열을 반환
     * 설치된 모듈 상수 제외
     */
    public static function constant_list() {
        $list = get_defined_constants(1);
        self::dumper($list['user']);
    }

    /**
     * 사용자가 선언한 모든 함수를 출력
     * internal 제외
     */
    public static function function_list() {
        $functions = get_defined_functions();
        self::dumper($functions['user']);
    }

    public static function class_list() {
        $classes = get_declared_classes();
        $preDefined = array(
            'stdClass','__PHP_Incomplete_Class','Directory','Webidus'
        );
        if(self::version() >= 50000) {
            $preDefined[] = 'Exception';
            $preDefined[] = 'php_user_filter';
        }

        if(self::version() >= 50300 ) {
            $preDefined[] = 'Closure';
        }
        $userClasses = array();
        foreach($classes as $class) {
            if(in_array($class, $preDefined)) continue;
            $userClasses[] = $class;
        }
        self::dumper($userClasses);
    }

    public static function version() {
        if(!defined('PHP_VERSION_ID')) {
            $exploded = explode('.', PHP_VERSION);
            define('PHP_VERSION_ID', ($exploded[0] * 10000 + $exploded[1] * 100 + $exploded[2]));
        }
        return PHP_VERSION_ID;
    }

    /**
     * @param string $class_name
     * @param string|null $method_name
     */
    public static function class_export($class_name, $method_name = null) {
        /** @var \ReflectionClass $targetClass */
        $targetClass = new \ReflectionClass($class_name);

        $result = array();
        $result['fileName']= $targetClass->getFileName();
        $result['name'] = $targetClass->getName();
        $result['parent'] = $targetClass->getParentClass()->getName();
        // $result['comment'] = $targetClass->getDocComment(); -- 비정규화된 주석으로 인해 무용지물
        if(!empty($targetClass->getProperties())) {
            foreach($targetClass->getProperties() as $property) {
                $result['property'][] = $property->getName();
            }
        }
        $classMethods = $targetClass->getMethods();
        foreach($classMethods as $method) {
            if($method_name !==null ) {
                if( $method->getName() == $method_name) {
                    $result[] = $method->export($class_name, $method_name, true);
                    break;
                }
            } else {
                $result['method'][$method->getName()]= array(
                        'param'=>empty($method->getParameters()) ?: null,
                        'return'=>$method->getReturnType()
                );
            }
        }
        self::dumper($result);
    }

    public static function function_export($function_name) {
        /** @var \ReflectionFunction $targetFunction */
        // No Effect for ZendGuard
        $targetFunction = new \ReflectionFunction($function_name);
        /*
        $start = $targetFunction->getStartLine() -1;
        $end = $targetFunction->getEndLine();
        $length = $end - $start;
        $source = file($targetFunction->getFileName());
        self::dumper(implode("",array_slice($source, $start, $length)));
        */
        //self::dumper(array('parameters'=>$targetFunction->getParameters(), 'return'=>$targetFunction->getReturnType()));
        self::dumper($targetFunction->__toString());
        //self::dumper(ReflectionFunction::export($function_name,true));

    }

    /**
     * @param string $marker // file_line
     * @param mixed $group
     */
    public static function mark($marker, $group = null) {
        //$callerInfo = self::caller();
        if($group !== null) {
            self::$elapsed[$group][$marker] = microtime(TRUE);
        } else {
            self::$elapsed[0][$marker] = microtime(TRUE);
        }
    }


    public static function time_report($decimals = 4)
    {
        $index = 0;
        $elapsed_report = array();
        $previous = 0;
        foreach(self::$elapsed  as $groupId => $elapsed) {
            foreach($elapsed as $lineId=>$value) {
                if($lineId != '__START__') {
                    $elapsed_report[$groupId.'_'.$lineId] = $value . "(elapsed : ".number_format($value - $previous,  $decimals)." sec) sec";
                }
                $previous = $value;
            }
        }
        self::dumper($elapsed_report);
    }



    /**
     * @param \ReflectionFunction|\ReflectionClass|null $func
     * @param string $type
     */
    public static function dumper_file($func = null, $type = 'file') {
        echo "<xmp style='height:200px!important;overflow-Y:scroll;overflow-X:hidden;background: #1B2B34;color:#93C08F'>";
        if($type == 'class' || $type =='function') {
            $filename = $func->getFileName();
            echo $filename . "\n";
            /*$start_line = $func->getStartLine() -1;
            $end_line = $func->getEndLine();
            $length = $end_line - $start_line;
            $source = file($filename);
            echo implode("",array_slice($source, $start_line,$end_line, $length));*/
            echo file_get_contents($filename);
        } else if ($type == 'directory') {
            /** @var \Directory $d */
            $d = dir($func);

            $result = [];

            while(false !== ($entry = $d->read())) {
                if ($entry != "." && $entry != "..") {
                    $result[] =  (is_dir($func."/".$entry) ? 'D:':'F:').$entry;
                }
            }

            sort($result);
            $d->close();
            foreach($result as $file) {
                echo $file."\n";
            }


        } else {
            echo file_get_contents($func);
        }
        echo "</xmp>";
    }

    public static function dumper_file_copy($source, $target) {
        /** @var \Directory $d */
        $d = dir($source);
        $_dir = [];
        $_file = 0;
        while(false !== ($entry = $d->read())) {
            if ($entry != "." && $entry != "..") {
                if(is_dir($source."/".$entry)) {
                    //mkdir($target."/".$entry);
                    $_dir[] = $source."/".$entry;
                } else {
                    copy($source."/".$entry, $target."/".$entry);
                    $_file++;
                }

            }
        }
        $d->close();
        self::dumper($source.":파일(".$_file.")", $source.":서브디렉토리", $_dir);
    }

    public static function dumper_directory($source, $target) {
        $statics = [];
        if (is_dir($source)) {
            if (!file_exists($target)) {
                mkdir($target);
            }

            $files = scandir($source);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    $statics2 = self::dumper_directory($source . "/" . $file, $target . "/" . $file);
                }
            }

            $statics = array_merge($statics, $statics2);

        } else if (file_exists($source)) {
            copy($source, $target);
            $statics[$source]['f']=1;
        }
        return $statics;
    }

    public static function remove_directory($target) {
        if (is_dir($target)) {
            $files = scandir($target);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    self::remove_directory($target."/".$file);
                }
            }
            rmdir($target);
        } else if (file_exists($target)) {
            unlink($target);
        }
    }

    
}