<?php
/**
 * Created by PhpStorm.
 * User: luohuanjun
 * Date: 17/1/17
 * Time: 下午3:54
 */

namespace app\models;


use Yii;
use yii\base\Model;


class Upgrade extends Model
{


    /**
     * 升级类型:全部
     */
    const TYPE_ALL = 1;
    /**
     * 升级类型:禁用
     */
    const TYPE_DISABLED = 2;
    /**
     * 升级类型:灰度百分数
     */
    const TYPE_GRAY_PERCENT = 3;
    /**
     * 升级类型:灰度人数
     */
    const TYPE_GRAY_NUMBER = 4;
    /**
     * 升级类型:条件升级
     */
    const TYPE_CONDITIONS = 5;

    public  $error='';


    /**
     * 获取升级类型
     * @param int $key
     * @return mixed
     */
    public static function getType($key = -1)
    {
        $type = [
                self::TYPE_ALL => '全部',
                self::TYPE_DISABLED => '禁用',
                self::TYPE_GRAY_PERCENT => '灰度百分数',
                self::TYPE_GRAY_NUMBER => '灰度人数',
                self::TYPE_CONDITIONS => '条件升级'
            ];
        if ($key > -1) {
            return $type[$key];
        }
        return $type;

    }

    /**
     * 条件语句规则验证
     * @param $rule   服务器端规则
     * @param $json   客户端参数
     * @return bool
     */
    public function compare_rule($rule,$client_arr){
        //$client_arr=json_decode($json,true);

        /*if(!preg_match('/(.+?)+\w+[\s]{0,}$/',$rule)){
            $msg='条件语句格式不正确';
            self::error($msg);
        }*/
        if(strstr($rule,'||')){
            $cmp=explode('||',$rule);
            foreach($cmp as $cmpv){
                $res=$this->compare_condition($client_arr,$cmpv);
                if($res==true){
                    return true;
                }
            }
        }elseif(strstr($rule,'&&')){
            $res=$this->compare_condition($client_arr,$rule);
            return $res;
        }else{
            $res=$this->compare_condition($client_arr,$rule);
            return $res;
        }
    }

    /**
     * 条件语句比较
     * @param $client_arr
     * @param $cmp
     * @return bool
     */
    private function compare_condition($client_arr,$cmp){

        if(strstr($cmp,'&&')){
            $cmp=explode('&&',$cmp);
        }else{
            $cmp=array($cmp);
        }
        $partern='/^([a-z][\w\s]+)(==|!=|<|>|>=|<=)([-\w\s\'\'\.]+)$/i';
        foreach($cmp as $i =>$cv){
            $cv=trim($cv);
            $cmp[$i]=trim($cv);
            if(preg_match('/[\d\w\s]+(==|!=)$/i',$cv)){
                $cmp[$i]=$cv."true";
            }
            if(!preg_match($partern,$cmp[$i])) {
                $this->error='条件语句格式不正确';
                return false;
            }
        }

        $version='';
        extract($client_arr);
        $condition=array();
        $flag=true;
        $flag_num=0;
        foreach($cmp as $i =>$cv){

            preg_match($partern,$cv,$match_res);

            $condition['param']=trim($match_res[1]);
            $condition['operator']=trim($match_res[2]);
            $condition['value']=(trim($match_res[3])=='true')?'':trim($match_res[3]);

            if(array_key_exists($condition['param'],$client_arr)){
                if($condition['param']=='version'){
                    $flag=$this->version_compare($condition['value'],$version,$condition['operator']);
                }else{
                    $cmd_key="\${$condition['param']} {$condition['operator']} '{$condition['value']}'";
                    $cmd_str='if('.$cmd_key.'){ return true;}else{ return false;}';

                    $flag=eval($cmd_str);
                }
                if($flag==false){
                    return false;
                }else{
                    $flag_num++;
                }
            }else{
                if($condition['operator']=='==' && ($condition['value'])==''){
                    $flag_num++;
                }elseif($condition['operator'] =='!=' && $condition['value']!=''){
                    $flag_num++;
                }else{
                    $flag_num--;
                }
            }
        }
        if($flag_num>0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 条件语句版本比较
     * @param $v1
     * @param $v2
     * @param $operator
     * @return bool
     */
    public function version_compare($v1,$v2,$operator){
        if($v1=='true' && empty($v2) && $operator =='=='){
            return true;
        }
        if($v1=='true' && !empty($v2) && $operator =='=='){
            return false;
        }
        if($v1=='true' && empty($v2) && $operator =='!='){
            return false;
        }
        if($v1=='true' && !empty($v2) && $operator =='!='){
            return true;
        }
        $v1_arr=explode('.',$v1);
        $v2_arr=explode('.',$v2);
        $com_res=$this->version_compare_dl($v1_arr,$v2_arr);
        if($com_res==0){
            //$v1==$v2
            $sign_op=array('>=','<=','==');
        }elseif($com_res==1){
            //$v1>$v2
            $sign_op=array('<=','<');
        }else{
            //$v1<$v2
            $sign_op=array('>=','>');
        }
        return in_array($operator,$sign_op);
    }

    /**
     * @param $v1_arr
     * @param $v2_arr
     * @param int $i
     * @return int
     * 递归比较版本号
     */
    public function version_compare_dl($v1_arr,$v2_arr,$i=0){


        if(!isset($v1_arr[$i])){
            $v1_arr[$i]=0;
        }
        if(!isset($v2_arr[$i])){
            $v2_arr[$i]=0;
        }
        if($v1_arr[$i]==0 && $v2_arr[$i]==0  && !isset($v1_arr[$i+1]) && !isset($v2_arr[$i+1])){
            return 0;
        }
        if($v1_arr[$i]>$v2_arr[$i]){
            return 1;
        }elseif($v1_arr[$i]==$v2_arr[$i]){
            return $this->version_compare_dl($v1_arr,$v2_arr,$i+1);
        }else{
            return 2;
        }
    }

    /**
     * 获取HashCode
     * @param $string
     * @return int
     */
    public static function getStringHashCode($string){
        $hash = 0;
        $stringLength = strlen($string);
        for($i = 0; $i < $stringLength; $i++){
            $hash  += $string[$i];
        }
        return $hash;
    }
    //字符转换为ascii码
    private function asc_encode($c)
    {
        $len = strlen($c);
        $a = 0;
        $scill='';
        while ($a < $len)
        {
            $ud = 0;
            if (ord($c{$a}) >=0 && ord($c{$a})<=127)
            {
                $ud = ord($c{$a});
                $a += 1;
            }
            else if (ord($c{$a}) >=192 && ord($c{$a})<=223)
            {
                $ud = (ord($c{$a})-192)*64 + (ord($c{$a+1})-128);
                $a += 2;
            }
            else if (ord($c{$a}) >=224 && ord($c{$a})<=239)
            {
                $ud = (ord($c{$a})-224)*4096 + (ord($c{$a+1})-128)*64 + (ord($c{$a+2})-128);
                $a += 3;
            }
            else if (ord($c{$a}) >=240 && ord($c{$a})<=247)
            {
                $ud = (ord($c{$a})-240)*262144 + (ord($c{$a+1})-128)*4096 + (ord($c{$a+2})-128)*64 + (ord($c{$a+3})-128);
                $a += 4;
            }
            else if (ord($c{$a}) >=248 && ord($c{$a})<=251)
            {
                $ud = (ord($c{$a})-248)*16777216 + (ord($c{$a+1})-128)*262144 + (ord($c{$a+2})-128)*4096 + (ord($c{$a+3})-128)*64 + (ord($c{$a+4})-128);
                $a += 5;
            }
            else if (ord($c{$a}) >=252 && ord($c{$a})<=253)
            {
                $ud = (ord($c{$a})-252)*1073741824 + (ord($c{$a+1})-128)*16777216 + (ord($c{$a+2})-128)*262144 + (ord($c{$a+3})-128)*4096 + (ord($c{$a+4})-128)*64 + (ord($c{$a+5})-128);
                $a += 6;
            }
            else if (ord($c{$a}) >=254 && ord($c{$a})<=255)
            { //error
                $ud = 0;
                $a++;
            }else{
                $ud = 0;
                $a++;
            }
            $scill .= "$ud";
        }
        return $scill;
    }



//将字符串转变成hashcode
    public static function getHashCode($s){
        $arr_str = str_split($s);
        $len = count($arr_str);
        $hash = 0;
        for($i=0; $i<$len; $i++){
            if(ord($arr_str[$i])>127){
                $ac_str = $arr_str[$i].$arr_str[$i+1].$arr_str[$i+2];
                $i+=2;
            }else{
                $ac_str = $arr_str[$i];
            }
            $hash = (int)($hash*31 + self::asc_encode($ac_str));
            //64bit下判断符号位
            //if(($hash & 0x80000000) == 0) {
            if($hash>0){
                //正数取前31位即可
                $hash &= 0x7fffffff;
            }
            else{
                //负数取前31位后要根据最小负数值转换下
                $hash = ($hash & 0x7fffffff) - 2147483648;
            }
        }
        return $hash;
    }
    /**
     * 条件语句规则验证
     * @param $rule   服务器端规则
     * @param $json   客户端参数
     * @return bool
     */
    public static function check_compare_rule($rule){
        //$client_arr=json_decode($json,true);

        /*if(!preg_match('/(.+?)+\w+[\s]{0,}$/',$rule)){
            $msg='条件语句格式不正确';
            self::error($msg);
        }*/
        if(strstr($rule,'||')){
            $cmp=explode('||',$rule);
            foreach($cmp as $cmpv){
                $res=self::check_compare_condition($cmpv);
                if($res==true){
                    return true;
                }
            }
        }elseif(strstr($rule,'&&')){
            $res=self::check_compare_condition($rule);
            return $res;
        }else{
            $res=self::check_compare_condition($rule);
            return $res;
        }
    }

    /**
     * 条件语句比较
     * @param $client_arr
     * @param $cmp
     * @return bool
     */
    public static function check_compare_condition($cmp){

        if(strstr($cmp,'&&')){
            $cmp=explode('&&',$cmp);
        }else{
            $cmp=array($cmp);
        }
        $partern='/^([a-z][\w\s]+)(==|!=|<|>|>=|<=)([-\w\s\'\'\.]+)$/i';
        foreach($cmp as $i =>$cv){
            $cv=trim($cv);
            $cmp[$i]=trim($cv);
            if(preg_match('/[\d\w\s]+(==|!=)$/i',$cv)){
                $cmp[$i]=$cv."true";
            }
            if(!preg_match($partern,$cmp[$i])) {
                return false;
            }
        }
        return true;
    }

}