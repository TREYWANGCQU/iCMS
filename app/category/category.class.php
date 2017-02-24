<?php
/**
 * @package iCMS
 * @copyright 2007-2017, iDreamSoft
 * @license http://www.idreamsoft.com iDreamSoft
 * @author coolmoo <idreamsoft@qq.com>
 */
class category {
    public static $appid = null;
    public static function set_appid($appid){
        self::$appid = $appid;
    }
    public static function init_sql($appid=null,$_sql=null){
        self::$appid && $appid = self::$appid;

        if($appid && !is_numeric($appid)){
            $appid = iPHP::appid($appid);
         }

        if(empty($appid)){
            $sql = '1 = 1';
            $_sql && $sql = $_sql;
        }else{
            $sql =" `appid`='$appid'";
            $_sql && $sql.=' AND '.$_sql;
        }

        return $sql;
    }

    public static function is_root($rootid="0"){
        $is = iDB::value("SELECT `cid` FROM `#iCMS@__category` where `rootid`='$rootid'");
        return $is?true:false;
    }
    public static function rootid($rootids=null,$appid=null) {
        if($rootids===null) return array();

        list($rootids,$is_multi)  = iSQL::multi_var($rootids);

        $sql  = iSQL::in($rootids,'rootid',false,true);
        $sql  = self::init_sql($appid,$sql);
        $data = array();
        $rs   = iDB::all("SELECT `cid`,`rootid` FROM `#iCMS@__category` where {$sql}",OBJECT);
        if($rs){
            $_count = count($rs);
            for ($i=0; $i < $_count; $i++) {
                if($is_multi){
                    $data[$rs[$i]->rootid][$rs[$i]->cid]= $rs[$i]->cid;
                }else{
                    $data[]= $rs[$i]->cid;
                }
            }
        }
        if(empty($data)){
            return;
        }
        return $data;
    }
    public static function multi_get($rs,$field,$appid=null) {
        $cids = iSQL::values($rs,$field,'array',null);
        $data = array();
        if($cids){
          $cids = iSQL::explode_var($cids);
          $appid && self::set_appid($appid);
          $data = (array) self::get($cids);
        }
        return $data;
    }
    public static function get($cids,$callback=null,$appid=null) {
        if(empty($cids)) return array();

        $field = '*';
        if(isset($callback['field'])){
            $field = $callback['field'];
        }

        list($cids,$is_multi)  = iSQL::multi_var($cids);

        $sql  = iSQL::in($cids,'cid',false,true);
        $sql  = self::init_sql($appid,$sql);
        $data = array();
        $rs   = iDB::all("SELECT {$field} FROM `#iCMS@__category` where {$sql}",OBJECT);
        if($rs){
            if($is_multi){
                $_count = count($rs);
                for ($i=0; $i < $_count; $i++) {
                    $data[$rs[$i]->cid]= self::item($rs[$i],$callback);
                }
            }else{
                if(isset($callback['field'])){
                    return $rs[0];
                }else{
                    $data = self::item($rs[0],$callback);
                }
            }
        }
        if(empty($data)){
            return;
        }
        return $data;
    }
    public static function item($category,$callback=null) {
        $category->iurl     = iURL::get('category',(array)$category);
        $category->href     = $category->iurl->href;
        $category->CP_ADD   = admincp::CP($category->cid,'a')?true:false;
        $category->CP_EDIT  = admincp::CP($category->cid,'e')?true:false;
        $category->CP_DEL   = admincp::CP($C->cid,'d')?true:false;
        $category->rule     = json_decode($category->rule,true);
        $category->template = json_decode($category->template,true);

        if($callback){
           $category = call_user_func_array($callback,array($category));
        }
        return $category;
    }
    public static function get_cid($rootid=null,$where=null,$appid=null) {
        $rootid===null OR $sql.= " `rootid`='$rootid'";

        $sql.= iSQL::where($where,true);
        $sql = self::init_sql($appid,$sql);

        $variable = iDB::all("SELECT `cid` FROM `#iCMS@__category` WHERE {$sql} ORDER BY `sortnum`  ASC",ARRAY_A);

        // var_dump(iDB::$last_query);

        $category = array();
        foreach ((array)$variable as $key => $value) {
            $category[] = $value['cid'];
        }
        return $category;
    }

    public static function get_root($cid="0",$root=null) {
        empty($root) && $root = iCache::get('category/rootid');
        $ids = $root[$cid];
        if(is_array($ids)){
            $array = $ids;
            foreach ($ids as $key => $_cid) {
              $array+=self::get_root($_cid,$root);
            }
        }
        return (array)$array;
    }
    public static function get_parent($cid="0",$parent=null) {
        if($cid){
            empty($parent) && $parent = iCache::get('category/parent');
            $rootid = $parent[$cid];
            if($rootid){
                return self::get_parent($rootid,$parent);
            }
        }
        return $cid;
    }
    public static function cache($one=false,$appid=null) {
        $sql = self::init_sql($appid);
        $rs  = iDB::all("SELECT * FROM `#iCMS@__category` WHERE {$sql} ORDER BY `sortnum`  ASC");
        $hidden = array();
        foreach((array)$rs AS $C) {
            $C['status'] OR $hidden[]        = $C['cid'];
            $dir2cid[$C['dir']]              = $C['cid'];
            $parent[$C['cid']]               = $C['rootid'];
            $rootid[$C['rootid']][$C['cid']] = $C['cid'];
            $app[$C['appid']][$C['cid']]     = $C['cid'];
        }

        foreach ((array)$app as $appid => $value) {
            iCache::set('category/appid.'.$appid,$value,0);
        }
        iCache::set('category/dir2cid',$dir2cid,0);
        iCache::set('category/hidden', $hidden,0);
        iCache::set('category/rootid',$rootid,0);
        iCache::set('category/parent',$parent,0);

        $domain_rootid = array();
        foreach((array)$rs AS $C) {
            if($C['domain']){
                $root = self::get_root($C['cid'],$rootid);
                $root && $domain_rootid+= array_fill_keys($root, $C['cid']);
            }
        }

        iCache::set('category/domain_rootid',$domain_rootid,0);unset($domain_rootid,$root);

        foreach((array)$rs AS $C) {
            $C = self::data($C);
            self::cahce_item($C,'C');
        }
        unset($rootid,$parent,$dir2cid,$hidden,$app,$rs,$C);

        gc_collect_cycles();
    }

    public static function cache_get($cid="0",$fix=null) {
        return iCache::get('category/'.$fix.$cid);
    }
    public static function cahce_item($C=null,$fix=null){
        if(!is_array($C)){
            $C = iDB::row("SELECT * FROM `#iCMS@__category` where `cid`='$C' LIMIT 1;",ARRAY_A);
        }
        iCache::set('category/'.$fix.$C['cid'],$C,0);
    }

    public static function cache_all($offset,$maxperpage,$appid=null) {
        $sql = self::init_sql($appid);
        $ids_array  = iDB::all("
            SELECT `cid`
            FROM `#iCMS@__category` {$sql} ORDER BY cid
            LIMIT {$offset},{$maxperpage};
        ");
        $ids   = iSQL::values($ids_array,'cid');
        $ids   = $ids?$ids:'0';
        $rs  = iDB::all("SELECT * FROM `#iCMS@__category` WHERE `cid` IN({$ids});");
        foreach((array)$rs AS $C) {
            $C = self::data($C);
            self::cahce_item($C,'C');
        }
        unset($$rs,$C,$ids_array);
    }
    public static function cahce_del($cid=null){
        if(empty($cid)){
            return;
        }
        iCache::delete('category/'.$cid);
        iCache::delete('category/C'.$cid);
    }

    public static function data($C){
        if($C['url']){
            $C['iurl']   = array('href'=>$C['url']);
            $C['outurl'] = $C['url'];
        }else{
            $C['iurl'] = (array) iURL::get('category',$C);
        }
        $C['url']    = $C['iurl']['href'];
        $C['link']   = "<a href='{$C['url']}'>{$C['name']}</a>";
        $C['sname']  = $C['subname'];

        $C['subid']  = self::get_root($C['cid']);
        $C['child']  = $C['subid']?true:false;
        $C['subids'] = implode(',',(array)$C['subid']);

        $C['dirs']   = self::data_dirs($C['cid']);
        $C['sappid'] = iCMS_APP_CATEGORY;

        $C = self::data_pic($C);
        $C = self::data_parent($C);
        $C = self::data_nav($C);

        is_string($C['rule'])    && $C['rule']     = json_decode($C['rule'],true);
        is_string($C['template'])&& $C['template'] = json_decode($C['template'],true);
	    is_string($C['metadata'])&& $C['metadata'] = metadata($C['metadata']);

		return $C;
    }
    public static function data_dirs($cid="0") {
        $C = self::cache_get($cid);
        $C['rootid'] && $dir.=self::data_dirs($C['rootid']);
        $dir.='/'.$C['dir'];
        return $dir;
    }
    public static function data_pic($C){
        $C['pic']  = is_array($C['pic'])?$C['pic']:get_pic($C['pic']);
        $C['mpic'] = is_array($C['mpic'])?$C['mpic']:get_pic($C['mpic']);
        $C['spic'] = is_array($C['spic'])?$C['spic']:get_pic($C['spic']);
        return $C;
    }
    public static function data_parent($C){
        if($C['rootid']){
            $root = self::cache_get($C['rootid']);
            $C['parent'] = self::data($root);
        }
        return $C;
    }
    public static function data_nav($C){
        $C['nav']      = '';
        $C['navArray'] = array();
        self::data_nav_array($C,$C['navArray']);
        krsort($C['navArray']);
        foreach ((array)$C['navArray'] as $key => $value) {
            $C['nav'].="<li><a href='{$value['url']}'>{$value['name']}</a><span class=\"divider\">".iUI::lang('iCMS:navTag')."</span></li>";
        }
        return $C;
    }
    public static function data_nav_array($C,&$navArray = array()) {
        if($C) {
            $navArray[]= array(
                'name' => $C['name'],
                'url'  => $C['iurl']['href'],
            );
            if($C['rootid']){
                $rc = (array)self::cache_get($C['rootid']);
                $rc['iurl'] = (array) iURL::get('category',$rc);
                self::data_nav_array($rc,$navArray);
            }
        }
    }
    public static function search_sql($cid,$field='cid'){
        if($cid){
            $cids  = (array)$cid;
            $_GET['sub'] && $cids+=categoryApp::get_ids($cid,true);
            $sql= iSQL::in($cids,$field);
        }
        return $sql;
    }
    public static function select_lite($permission='',$scid="0",$cid="0",$level = 1,$url=false,$where=null) {
        $cid_array  = (array)category::get_cid($cid,$where);//获取$cid下所有子栏目ID
        $cate_array = (array)category::get($cid_array);     //获取子栏目数据
        $root_array = (array)category::rootid($cid_array);  //获取子栏目父栏目数据
        foreach($cid_array AS $root=>$_cid) {
            $C = (array)$cate_array[$_cid];
            if(admincp::CP($_cid,$permission) && $C['status']) {
                $tag      = ($level=='1'?"":"├ ");
                $selected = ($scid==$_cid)?"selected":"";
                $text     = str_repeat("│　", $level-1).$tag.$C['name']."[cid:{$_cid}]".($C['url']?"[∞]":"");
                ($C['url'] && !$url) && $selected ='disabled';
                $option.="<option value='{$_cid}' $selected>{$text}</option>";
            }
            $root_array[$_cid] && $option.= self::select_lite($permission,$scid,$C['cid'],$level+1,$url);
        }
        return $option;
    }
    public static function select($permission='',$scid="0",$cid="0",$level = 1,$url=false,$where=null) {
        $cc = iDB::value("SELECT count(*) FROM `#iCMS@__category`");
        if($cc<=1000){
            return self::select_lite($permission,$scid,$cid,$level,$url,$where);
        }else{
            $array = iCache::get('category/cookie');
            foreach((array)$array AS $root=>$_cid) {
                $C = category::cache_get($_cid);
                if($C['status']) {
                    $selected = ($scid==$_cid)?"selected":"";
                    $text     = $C['name']."[cid:{$_cid}][pid:{$C['pid']}]";
                    $option  .= "<option value='{$_cid}' $selected>{$text}</option>";
                }
            }
            return $option;
        }
    }
}
