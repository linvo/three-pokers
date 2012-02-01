<?php
/**
 * 诈金花（联机版）
 *
 * linvo@foxmail.com
 * 2011/12/21
 */

session_start();
header('Content-Type:text/html;Charset=utf-8;');
error_reporting(E_ALL);
date_default_timezone_set("PRC");


/** 配置 begin */
$config['db']['type'] = 'mysql'; //切换存储方式(mysql | redis)

$config['redis']['host'] = '192.168.1.94';
$config['redis']['port'] = '8964';
$config['redis']['ns']   = 'zjh';
$config['redis']['username']   = '';
$config['redis']['password']   = '';

$config['mysql']['host'] = 'localhost';
$config['mysql']['port'] = '3306';
$config['mysql']['ns']   = 'zjh';
$config['mysql']['username']   = 'root';
$config['mysql']['password']   = '';

include strtolower($config['db']['type']) . ".class.php";
$config['db']['host'] = $config[$config['db']['type']]['host'];
$config['db']['port'] = $config[$config['db']['type']]['port'];
$config['db']['ns']   = $config[$config['db']['type']]['ns']; 
$config['db']['username']   = $config[$config['db']['type']]['username']; 
$config['db']['password']   = $config[$config['db']['type']]['password']; 

$config['auto_account'] = 1; //自动随机起名（1|0）
$config['auto_account'] = 1; //自动随机起名（1|0）
$config['flush']['timeout'] = 4; //自动刷新时间间隔
$config['room']['timeout']  = 3600; //房间的生命期限
$config['max']['room']  = 10; //房间数量
$config['max']['bot']   = 16; //电脑数量（<=16）
$config['borrow']       = 0; //是否允许负资产(1|0)

$config['style'] = array(
        'baozi'         => '豹子',
        'tonghuashun'   => '同花顺',
        'tonghua'       => '金花',
        'shunzi'        => '顺子',
        'duizi'         => '对子',
        );
$config['status'] = array(
        'ready'     => '准备',
        'playing'   => '游戏中',
        'pass'      => '弃牌',
        );
/** 配置 end */


/** 获取基本信息 */
$account = getParam('account', 'S');
$room_number = getParam('room_number', 'S');
$action = getParam('action', 'P');
$msg = '';

/** 后门 */
$bd['pwd'] = getParam('p');//密码
$bd['room'] = getParam('r');//房间号
if ($bd['pwd'] == 'linvo') del_data($bd['room']); //强拆

/** 当前操作 */
switch ($action){
    case '更换帐号': //登出 
        unset($_SESSION['account']);
        left_room();
        break;
    case '是也': //登录
        $account_p = getParam('account', 'P');
        //if (preg_match('/^[a-zA-Z]{3,10}$/', $account_p)){
        if(strlen($account_p) < 20){
            $_SESSION['account'] = $account_p;
        } else {
            left_room();
        }
        break;
    case '进入房间':   
        $room_number_p = (int)getParam('room_number', 'P');
        $data = load_data($room_number_p); 
        if ($data && find_key($data, $account) !== Null){
            $msg = '该房间中已有与您重名的玩家，请更换帐号或选择其他房间';
            break; 
        } else if ($data && count($data['gamers']) >= 17){
            $msg = '该房间人数已达上限，请选择其他房间';
            break; 
        }
        $now = time();
        /** 新建房间 */
        if (!$data || $now - $data['time_room'] > $config['room']['timeout']){
            $data = array(
                    'gamers' => array(),
                    'status'=> 'ready', //本局状态(ready/playing)
                    'round' => array(
                        'master'    => $account, //庄家
                        'speaker'   => $account, //讲话者
                        'winner'    => '',  //赢家
                        'openner'   => '',  //开牌者
                        'show_poker'=> 0,   //是否显示所有扑克（结束时用）
                        'gamer_total'=> 0,  //本局当前存活玩家数量
                        'chip_sum'  => 0,   //本局下注总额
                        'chip_last' => 5,   //当前下注额度
                        ),
                    'time_room' => $now,
                    );
            $bot_total = (int)getParam('bot_total', 'P');
            for ($i=$bot_total; $i>0; --$i){
                $bots[] = array(
                        'account'   => '电脑'.($bot_total-$i+1).'号', //玩家帐号
                        'isbot'     => 1,       //玩家是否是电脑
                        'pokers'    => array('#', '#', '#'),        //手牌
                        'status'    => 'ready', //玩家状态(ready/playing/pass)
                        'chips'     => 1000,    //玩家资产
                        'chip_sum'  => 0,       //玩家本局下注总数
                        'chip_last' => 0,       //玩家本局最后次下注数
                        'chip_times'=> 0,       //玩家本局下注次数
                        'viewed'    => 1,       //玩家本局是否已看牌                   
                        );
            }
            $enter = True;
        } else {
            /** 游戏中 */
            if ($data['status'] == 'playing'){
                $msg = $room_number_p . '号房间正在游戏中';
                $enter = False;
            }
            /** 准备中 */
            else if ($data['status'] == 'ready'){
                $enter = True;
            }
        }
        if ($enter){
            /** 新玩家信息 */
            $data_gamer_new = array(
                    'account'   => $account,//玩家帐号
                    'isbot'     => 0,       //玩家是否是电脑
                    'pokers'    => array('#', '#', '#'), //手牌
                    'status'    => 'ready', //玩家状态(ready/playing/pass)
                    'chips'     => 1000,    //玩家资产
                    'chip_sum'  => 0,       //玩家本局下注总数
                    'chip_last' => 0,       //玩家本局最后次下注数
                    'chip_times'=> 0,       //玩家本局下注次数
                    'viewed'    => null,    //玩家本局是否已看牌
                    );
            $data = add_gamer($data, $data_gamer_new);
            if (isset($bots) && !empty($bots)){
                $data = add_gamer($data, $bots, True);
            }
            $result = send_data($room_number_p, $data);
            $_SESSION['room_number'] = $room_number_p;
        }
        break;
    case '发牌':   
        $data = load_data($room_number); 
        if (!$data) left_room();
        $data = new_round($data);
        $data = opt_deal($data);
        $keys = array();
        foreach ($data['gamers'] as $k => $v){
            if ($config['borrow'] || $v['chips'] > 0) $keys[] = $k; //需要发牌玩家的键
        }
        foreach ($keys as $v){
            $data = chipin($data, $data['gamers'][$v]['account'], 5, 0); //下底注
        }
        $result = send_data($room_number, $data, True);
        break;
    case '看牌':   
        $data = load_data($room_number); 
        if (!$data) left_room();
        $data = view_pokers($data, $account);
        $result = send_data($room_number, $data, True);
        break;
    case '下注':   
        $data = load_data($room_number); 
        if (!$data) left_room();
        if (is_finish($data, $account)){ //其他人都退出
            $data = finish($data);
        } else {
            $chip_gamer = getParam('chip_gamer', 'P');
            $data = chipin($data, $account, $chip_gamer);
            $data = next_turn($data, $account);
        }
        $result = send_data($room_number, $data, True);
        break;
    case '弃牌':   
        $data = load_data($room_number); 
        if (is_finish($data, $account)){ //其他人都退出
            $data = finish($data);
        } else {
            $data = pass_pokers($data, $account);
        }
        $result = send_data($room_number, $data);
        break;
    case '开牌':   
        $data = load_data($room_number); 
        if (is_finish($data, $account)){ //其他人都退出
            $data = finish($data);
        } else {
            $data = chipin($data, $account, 0, True);
            list($winner, $loster, $paixing) = compare($data, $account);
            $data['round']['openner'] = $account;
            $data['gamers'][$loster['key']]['status'] = 'ready';
            $data = finish($data);
        }
        $result = send_data($room_number, $data, 0);
        break;
    case '退出房间':   
        $data = load_data($room_number); 
        if (is_finish($data, $account) || only_bots($data, $account)){ //除机器人外其他人都退出
            del_data($room_number); //注销该房间
            left_room();
        } else {
            if ($data['round']['speaker'] != $account){
                $data = pass($data, $account); 
                $key = find_key($data, $account);
                if ($key !== False){
                    unset($data['gamers'][$key]);    
                    $result = send_data($room_number, $data);
                }
                left_room();
            }
        }
        break;
    case '刷新':
    default:
        if (!empty($room_number)){
            $data = load_data($room_number); 
            if (!$data) left_room();
            if (is_finish($data, $account) && $data['round']['chip_sum']){ //其他人都退出
                $data = finish($data, $account);
                $result = send_data($room_number, $data);
            } else {
                /* 有严重bug 废弃！
                if ($data['round']['gamer_total'] > 1 && $data['status'] == 'playing') { //讲话者超时

                    // 1.踢出
                    foreach ($data['gamers'] as $k => $v){
                        if ($v['account'] == $data['round']['speaker']){
                            $playing = $v['status'] == 'ready' ? False : True; 
                            $data = pass($data, $account, $playing); 
                            unset($data['gamers'][$k]);    
                        }   
                    }
                    $result = send_data($room_number, $data);
                    // 2.强行弃牌 
                    $data = pass($data, $data['round']['speaker']);
                    // 对决情况下弃牌本局结束 
                    if (is_finish($data, $data['round']['speaker'])){
                        $data = finish($data);
                        $gamer_time = 0;
                    } else {
                        $data = next_turn($data, $data['round']['speaker']);
                        $gamer_time = True;
                    }
                    $result = send_data($room_number, $data, $gamer_time);
                }   
            */
            }
        }
}

/** 登录页 */
$account = getParam('account', 'S');
if (!$account){
    page_login();   
}

/** 房间选择页 */
$room_number = getParam('room_number', 'S');
if (empty($room_number)){
    page_menu($msg);
}

/** 游戏房间页 */
page_room($data, $account);

//////////////////////////////////////////////////////////////////////
/** 模板输出函数 */
/**
 * 页面头部
 */
function tpl_header(){
    echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>Three Pokers</title></head><body style="background:#333;color:#fff">';
}
/**
 * 页面底部
 */
function tpl_footer(){
    die('</body></html>');
}

/**
 * 游戏房间
 */
function page_room($data, $account){
    tpl_header();
    tpl_title($data);
    echo '<br /><br />';
    tpl_list($data, $account);
    echo '<br />';
    tpl_button($data, $account);
    tpl_footer();
}

/**
 * 游戏房间 - 顶部信息
 */
function tpl_title($data){
    echo '房间号：' . getParam('room_number', 'S');
    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    echo '本局下注总数：'.$data['round']['chip_sum'];
}

/**
 * 游戏房间 - 玩家列表
 */
function tpl_list($data, $account){
    global $config;
    echo '<style type="text/css">
            table {width:100%; text-align:center;border:1px solid #aaa; border-width:0 0 1px 1px;}
            table th {height:25px; background:#222;}
            table th {border:1px solid #aaa; border-width:1px 1px 0 0;}
            table td {border:1px solid #aaa; border-width:1px 1px 0 0;}
        </style>';
    echo '<div><table border="0" width="100%"><tr>';
    echo '<th>玩家</th><th>资产</th><th>状态</th><th>手牌</th><th>明暗注</th><th>本局下注筹码</th><th>下注次数</th>';
    echo '</tr>';
    if (!empty($data)){
        foreach ($data['gamers'] as $gamer){
            if ($config['borrow']){
                $out = False;
            } else {
                $out = ($gamer['chips'] <= 0 && $gamer['status'] != 'playing') ? True : False; //是否已出局
            }

            echo '<tr>';
            if ($account == $gamer['account']){
                $isself = True; 
                $span_account = '<span style="color:#0f0;"><strong>'.$gamer['account'].'</strong></span>';
            } else {
                $isself = False; 
                $span_account = '<span><strong>'.$gamer['account'].'</strong></span>';
            }
            $ismaster   = $data['round']['master']  == $gamer['account'] ? '（庄家）' : '';
            $isspeaker  = $data['round']['speaker'] == $gamer['account'] ? '<span style="font-size:25px;color:#ff0">☞</span> ' : '';
            $iswinner   = $data['round']['winner']  == $gamer['account'] ? '<span style="color:#ff0">【赢家】</span>' : '';
            $isopenner  = $data['round']['openner'] == $gamer['account'] ? '<span style="color:red">（开牌）</span>' : '';
            echo '<td>'.$isspeaker.$span_account.$ismaster.$isopenner.$iswinner.'</td>';    

            /** 资产 */
            $color = $gamer['chips'] > 0 ? '#0f0' : 'red';
            echo '<td><span style="color:'.$color.';">'.$gamer['chips'].'</span></td>';    

            /** 状态 */
            echo '<td>';
            if ($out){
                echo '<span style="color:#ccc">出局</span>';
            } else {
                $color = in_array($gamer['status'], array('ready', 'pass')) ? '#0f0' : 'red';
                echo '<span style="color:'.$color.'">' . $config['status'][$gamer['status']] . '</span>';
            }
            echo '</td>';    

            /** 手牌 */
            echo '<td>';    
            foreach ($gamer['pokers'] as $v){
                if (!$out && ($data['round']['show_poker'] || ($isself && isset($gamer['viewed']) && $gamer['viewed']==1))) $poker = "[$v] ";
                else $poker = '[#] ';
                echo '<span style="font-size:25px">' . $poker . '</span>';
            }
            if (!$out && $data['round']['show_poker']){
                /** 显示各玩家牌型 */
                $poker_style = get_poker_style($data, $gamer['account']); 
                if ($poker_style){
                    foreach ($poker_style as $k => $v){
                        if (!empty($v)) echo ' <span style="color:red">【' . $config['style'][$k] . '】</span>';
                    }
                }
            }
            echo '</td>';    

            /** 明暗注 */
            echo '<td>';
            if (!isset($gamer['viewed'])){
                echo '未知';    
            } else if ($gamer['viewed'] == 1){
                echo '<span style="color:#0c0">明注</span>';    
            } else if ($gamer['viewed'] == 0){
                echo '<span style="color:#06f">暗注</span>';    
            }
            echo '</td>';    
            echo '<td>';
            echo $out ? '-' : $gamer['chip_sum'].' (+'.$gamer['chip_last'].')';
            echo '</td>';    
            echo '<td>'.$gamer['chip_times'].'</td>';    
            echo '<tr>';
        }
    }
    echo '</table></div>';
}

/**
 * 游戏房间 - 操作按钮
 */
function tpl_button($data, $account){
    global $config;
    $dis_deal = $dis_view = $dis_chip = $dis_pass = $dis_open = $dis_exit = ' disabled="disabled" ';
    if (($data['round']['gamer_total']>1 || count($data['gamers'])>1) && 
        $data['status'] == 'ready' && $data['round']['master'] == $account){
        $dis_deal = '';
    }
    if ($data['status'] == 'playing' && $data['round']['speaker'] == $account){
        $dis_view = '';
        $dis_chip = '';
        $dis_pass = '';
        if ($data['round']['gamer_total'] <= 2){
            $dis_open = '';
        }
    }
    if ($data['round']['gamer_total']<2 || $data['round']['speaker']!=$account) $dis_exit = '';
    echo '<div><form action="" method="post" onsubmit="return chkform(this)">';
    echo ' <input type="submit" name="action" value="发牌" '.$dis_deal.' /> |';    
    echo ' <input type="submit" name="action" value="看牌" '.$dis_view.' /> ';    
    $playing = True;
    foreach ($data['gamers'] as $k => $v){
        if ($v['account'] == $account && in_array($v['status'], array('ready','pass'))){
            $playing = False; 
            $key = $k;
            break;
        }   
    }
    list($key, $x) = chip_X($data, $account, $playing);
    $x = $x ? $x : 1; // 防止浏览器死循环
    $data['round']['chip_last'] = !isset($data['round']['chip_last']) ? 5 : $data['round']['chip_last'];// 防止浏览器死循环
    $option_str = '';
    for ($i=$data['round']['chip_last']*$x; $i!=0&&$i<=$data['gamers'][$key]['chips']; $i*=2){
        $option_str .= '<option value="'.$i.'">'.$i.'</option>';
    }
    /** 资金不足则禁止下注和开牌 */
    if (empty($option_str)){
        $option_str = '<option value="'.$i.'">'.$i.'</option>';
        if (!$config['borrow']){
            $dis_chip = $dis_open = ' disabled="disabled" ';
        }
    }
    echo ' <input type="submit" name="action" value="下注" '.$dis_chip.' />';    
    echo '<select name="chip_gamer" '.$dis_chip.'>';
    echo $option_str;
    echo '</select> |';
    echo ' <input type="submit" name="action" value="弃牌" '.$dis_pass.' /> ';    
    echo ' <input type="submit" name="action" value="开牌" '.$dis_open.' /> |';    
    echo ' <input type="submit" id="fb" name="action" value="刷新" /> ';
    echo '<span style="color:#aaa;font-size:12px;">每' . $config['flush']['timeout'] . '秒自动刷新</span>';    
    echo '<span style="float:right;color:red;font-size:12px;">请勿直接关闭浏览器！>>> <input type="submit" name="action" value="退出房间" '.$dis_exit.' /></span>';    
    echo '</form></div>';
    echo '<script>function chkform(o){o.Submit.disabled=true; return true;}</script>'; //防止重复提交表单
    /** 自动刷新 */
    echo '<script type="text/javascript">setInterval(function(){document.getElementById("fb").click();}, '.$config['flush']['timeout'].'000);</script>'; 
}

/**
 * 房间选择页
 */
function page_menu($msg = ''){
    global $config;
    tpl_header();
    echo '<div><form action="" method="post">';
    echo '<h3>哎呀，<span style="color:#0f0">'.getParam('account', 'S'). '</span>驾到！您里面儿请~</h3>';
    echo '房间号：<select name="room_number">';
    for ($i=1; $i<$config['max']['room']+1; ++$i){
        echo '<option value="'.$i.'">'.$i.'</option>';    
    }
    echo '</select>';
    echo '&nbsp;&nbsp;&nbsp;&nbsp;机器人个数：<select name="bot_total">';
    for ($i=0; $i<$config['max']['bot']+1; ++$i){
        echo '<option value="'.$i.'">'.$i.'</option>';    
    }
    echo '</select>';
    echo '<br /><br /><input type="submit" name="action" value="进入房间" />';
    echo '&nbsp;<input type="submit" name="action" value="更换帐号" />';
    echo '<h4 style="color:red">'.$msg.'</h4>';
    echo '</form></div>';
    tpl_footer();
}

/**
 * 登录页
 */
function page_login(){
    global $config;
    tpl_header();
    $account_rand = '';
    /** 随机起名 */
    if ($config['auto_account']){
        include 'c.php';
        $len = mt_rand(3, 3);
        for ($i=0; $i<$len; ++$i){
            $account_rand .= $account_lib[array_rand($account_lib)];    
        }
    }
    echo '<h3>敢问阁下尊姓大名？</h3>';
    echo '<div><form action="" method="post">';
    echo '<input type="text" maxlength="10" size="10" name="account" value="'.$account_rand.'" />';
    echo '<input type="submit" name="action" value="  是也  " />';
    if ($config['auto_account']){
        echo ' <input type="submit" name="action" value="换一个" />';
    }
    echo '</form></div>';
    tpl_footer();
}

/** 工具函数 */

/**
 * 获取参数
 *
 * @param string 参数名
 * @param string 参数类型
 * @param bool 是否是递归调用
 * @return string 
 */
function getParam($key, $type = 'GP', $inner = FALSE)
{
    if (!$inner)
    {
        $type = strtoupper($type);
        $num = strlen($type);
        for ($i=0; $i<$num; ++$i)
        {
            $ret = getParam($key, $type{$i}, TRUE);
            if ($ret !== '') break;
        }
    } else {
        switch ($type)    
        {
            case 'G':
                $ret = isset($_GET[$key]) ? $_GET[$key] : '';
                break;
            case 'P':
                $ret = isset($_POST[$key]) ? $_POST[$key] : '';
                break;
            case 'S':
                $ret = isset($_SESSION[$key]) ? $_SESSION[$key] : '';
                break;
            case 'C':
                $ret = isset($_COOKIE[$key]) ? $_COOKIE[$key] : '';
                break;
            default:
                $ret = '';
        }
    }
    return trim($ret);
}

/**
 * 页面跳转
 *
 * @param string 跳转地址
 * @return void
 */
function location($url = ''){
    $url = empty($url) ? $_SERVER['PHP_SELF'] : $url;
    header('Location:' . $url);
    exit;
}

/** 退出房间 */
function left_room(){
    unset($_SESSION['room_number']);
    location();
}
/** 输出调试信息 */
function debug($var, $printr = 0){
    echo '<pre>';
    if ($printr) print_r($var);
    else var_dump($var);
    echo '</pre>';
}
/**
 * 将改变后的牌局信息写入db
 *
 * @param int 房间号
 * @param array 数据结构
 * @return int
 */
function send_data($room_number, $data){
    global $config;
    $dbtype = $config['db']['type'];
    $fun = '_send_data_by_' . $dbtype;
    $ret = $fun($room_number, json_encode($data));
    return $ret;
}
/**
 * 从db中获取当前牌局信息
 *
 * @param int 房间号
 * @return array
 */
function load_data($room_number){
    global $config;
    if (!$room_number) return $room_number;
    $dbtype = $config['db']['type'];
    $fun = '_load_data_by_' . $dbtype;
    $ret = $fun($room_number);
    if ($ret) $ret = json_decode($ret, True);
    return $ret;
}
/**
 * 从db中删除房间信息
 *
 * @param int 房间号
 * @return array
 */
function del_data($room_number){
    global $config;
    if (!$room_number) return $room_number;
    $dbtype = $config['db']['type'];
    $fun = '_del_data_by_' . $dbtype;
    $ret = $fun($room_number);
    return $ret;
}

function _load_data_by_redis($room_number){
    global $config;
    $redis = new Predis_Client(array('host' => $config['db']['host'], 'port' => $config['db']['port']));
    try{
        $ret = $redis->hget($config['db']['ns'], $room_number);
    } catch (PredisException $e) {
        $ret = False;
    }
    return $ret;
}
function _send_data_by_redis($room_number, $data){
    global $config;
    $redis = new Predis_Client(array('host' => $config['db']['host'], 'port' => $config['db']['port']));
    try{
        $ret = $redis->hset($config['db']['ns'], $room_number, $data);
    } catch (PredisException $e) {
        $ret = False;
    }
    return $ret;
}
function _del_data_by_redis($room_number){
    global $config;
    $redis = new Predis_Client(array('host' => $config['db']['host'], 'port' => $config['db']['port']));
    try{
        $ret = $redis->hdel($config['db']['ns'], $room_number);
    } catch (PredisException $e) {
        $ret = False;
    }
    return $ret;
}

function _load_data_by_mysql($room_number){
    global $config;
    $db = MySql::getInstance($config['db']);
    $result = $db->execute("SELECT * FROM `room` WHERE `k`='{$room_number}' LIMIT 1");
    if ($result && !empty($result)) {
        $ret = $result[0]['v']; 
    } else {
        $ret = $result;    
    }
    return $ret;
}
function _send_data_by_mysql($room_number, $data){
    global $config;
    $data = addslashes($data);
    $db = MySql::getInstance($config['db']);
    $result = $db->execute("REPLACE INTO `room`(`k`,`v`) VALUES('{$room_number}','{$data}')");
    $ret = $result;   
    return $ret;
}
function _del_data_by_mysql($room_number){
    global $config;
    $db = MySql::getInstance($config['db']);
    $result = $db->execute("DELETE FROM `room` WHERE `k`='{$room_number}'");
    $ret = $result;   
    return $ret;
}
/**
 * 新玩家进入房间
 *
 * @param array 当前数据结构
 * @param array 新玩家数据
 * @param bool 是否是多名新玩家
 * @return array 新数据结构
 */
function add_gamer($data, $gamer_new, $islist = False){
    $news = $islist ? $gamer_new : array($gamer_new);
    $data['gamers'] = array_merge($data['gamers'], $news);    
    $data['round']['gamer_total'] += count($news);
    return $data;
}


/** 
 * 发牌
 */
function opt_deal($data){
    global $config;
    /** 生成52张牌 */
    $shcd = array(
            'S' => '&spades;',	
            'H' => '<span style="color:red">&hearts;</span>',
            'C' => '&clubs;',
            'D' => '<span style="color:red">&diams;</span>',
            );
    $num = array('1'=>'A','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10','11'=>'J','12'=>'Q','13'=>'K');
    $pokers = array();
    foreach ($shcd as $v){
        foreach ($num as $vv){
            $pokers[] = $v.'<strong>'.$vv.'</strong>';
        }   
    };

    /** 洗牌 */ 
    for ($i=0; $i<5; ++$i) shuffle($pokers); 
    
    /** 抬牌 */ 
    $offset = mt_rand(0, 52);
    $pokers_1 = array_slice($pokers, $offset, count($pokers)-$offset); //牌堆1
    $pokers_2 = array_slice($pokers, 0, $offset); //牌堆2

    /** 揭牌 */
    /** 暂时没做到庄家先揭牌 */
    for ($i=0; $i<3; ++$i){
        foreach ($data['gamers'] as $k => $v){
            if ($config['borrow'] || $v['chips'] > 0){
                $poker = array_pop($pokers_1); //先从牌堆1揭牌
                if (!$poker){
                    $poker = array_pop($pokers_2); //牌堆1揭完则从牌堆2揭
                }
                $data['gamers'][$k]['pokers'][$i] = $poker; 
            }
        }    
    }
    $data['status'] = 'playing';
    
    return $data;
}

/**
 * 转移讲话者到下一个
 *
 * @param array 数据结构
 * @param array 当前用户
 * @return array 
 */
function next_turn($data, $account){
    $mark = False; //下一个为讲话者
    $found = False;
    do{
        foreach ($data['gamers'] as $k => $v){
            if ($mark && $v['status']=='playing'){
                $key = $k;
                $mark = False;
                $found = True;
                break;
            }
            if ($v['account'] == $account){
                $mark = True;    
            }
        }
    }while(!$found);
    /** 下一个从头开始 */
    //if ($mark) $key = 0;
    $data['round']['speaker'] = $data['gamers'][$key]['account'];
    /** 如果下一个讲话者是电脑，自动执行电脑 */
    if ($data['gamers'][$key]['isbot']){
        $data = do_by_Bot($data, $data['round']['speaker']); 
    }
    return $data;
}

/**
 * 看牌
 */
function view_pokers($data, $account){
    foreach ($data['gamers'] as $k => $v){
        if ($v['account'] == $account) $data['gamers'][$k]['viewed'] = 1;   
    }
    return $data;
}

/**
 * 下注
 *
 * @param array 
 * @param string 
 * @param int 下注数
 * @param bool 是否是开牌（开牌时忽略chip参数）（0:发牌下底注）
 * @return array 
 */
function chipin($data, $account, $chip, $isopen = False){
    list($key, $x) = chip_X($data, $account);
    if ($isopen !== 0){
        if ($isopen){
            $chip = $data['round']['chip_last'] * 2;
        }
        if ($x==1 && is_null($data['gamers'][$key]['viewed'])) {
            $data['gamers'][$key]['viewed'] = 0; //玩家下暗注
        } else if ($x==1 && $data['gamers'][$key]['viewed']==0) {
            $data['gamers'][$key]['viewed'] = 0; //玩家下暗注
        } 
        if ($isopen && $x==2) {
            $chip *= 2; //开牌时，玩家下明注
        }
    } else {
        $x = 1;    
    }
    $data['gamers'][$key]['chips'] -= $chip; 
    $data['gamers'][$key]['chip_sum'] += $chip; //玩家本局下注总数
    $data['gamers'][$key]['chip_last'] = $chip; //玩家本局最后次下注数
    $data['gamers'][$key]['chip_times']++;      //玩家本局下注次数
    $data['round']['chip_sum'] += $chip;        //本局下注总数
    $data['round']['chip_last'] = $chip / $x;   //当前下注额度
    return $data;
}

/**
 * 查找某玩家的玩家键
 */
function find_key($data, $account){
    $key = Null;
    foreach ($data['gamers'] as $k => $v){
        if ($v['account'] == $account){
            $key = $k;    
        }   
    }
    return $key;
}

/** 
 * 计算下注倍数及用户键位
 * 
 * @return array($key, $x)
 */
function chip_X($data, $account, $playing = True){
    $viewed = $viewed_other = 0; //默认未看牌
    $x = 1; //下注倍数
    foreach ($data['gamers'] as $k => $v){
        if ($playing && $v['status'] != 'playing') continue;
        if ($v['account'] == $account){
            $key = $k; //自己的键位
            $viewed = $v['viewed'];
        } else {
            $viewed_other = !$viewed_other ? 0 : $v['viewed'];    
        }
    }
    if ($viewed && !$viewed_other){
        $x = 2; //有其他玩家下暗注而自己是明注时，需翻倍下注
    }
    if (!isset($key)) die('Error:chip_X()');
    $ret = array($key, $x); //键位, 倍数
    return $ret;
}

/**
 * 机器人操作
 */
function do_by_Bot($data, $account){
    global $config;
    $chip = chip_AI($data, $account);
    /** 开牌 */
    if ($chip === True){
        list($key, $x) = chip_X($data, $account);
        if (!$config['borrow'] && $data['gamers'][$key]['chips'] < $data['round']['chip_last'] * $x * 2){
            $data = pass_pokers($data, $account); //资金不足，弃牌
        } else {
            $data = chipin($data, $account, 0, True);
            list($winner, $loster, $paixing) = compare($data, $account);
            $data['round']['openner'] = $account;
            $data['gamers'][$loster['key']]['status'] = 'ready';
            $data = finish($data);
        }
        /** 弃牌 */
    } else if ($chip === False) {
        $data = pass_pokers($data, $account);
    /** 下注 */
    } else {
        list($key, $x) = chip_X($data, $account);
        if (!$config['borrow'] && $data['gamers'][$key]['chips'] < $data['round']['chip_last'] * $x){
            $data = pass_pokers($data, $account); //资金不足，弃牌
        } else {
            $data = chipin($data, $account, $chip * $x);
            $data = next_turn($data, $account);
        }
    }
    return $data;
}

/**
 * 弃牌流程
 */
function pass_pokers($data, $account){
    $data = pass($data, $account);
    /** 对决情况下弃牌本局结束 */
    if (is_finish($data, $account)){
        $data = finish($data);
    } else {
        $data = next_turn($data, $account);
    }
    return $data;
}

/**
 * 电脑AI
 *
 * @return True:开牌 | False:弃牌 | int:下注
 */
function chip_AI($data, $account){
    $min = $data['round']['chip_last'];
    $all = $data['round']['chip_sum'];
    $key = find_key($data, $account);
    $pc = $data['gamers'][$key]['chips'];

    $chip_times = $data['gamers'][$key]['chip_times'];
    $poker_style = get_poker_style($data, $account);
    $paixing = '';
    foreach ($poker_style as $k => $v){
        if ($v) $paixing = $k;    
    }
    switch ($paixing){
        case 'baozi':
        case 'tonghuashun':
            if ($chip_times == 0) $ret = mt_rand(0, 2) == 0 ? $min * mt_rand(4, 4) : $min;
            else if ($chip_times == 1) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 2) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 3) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 4) $ret = $min * mt_rand(2, 2);
            else if ($chip_times == 5) $ret = $min * mt_rand(4, 4);
            else if ($chip_times == 6) $ret = $min * mt_rand(2, 2);
            else if ($chip_times == 7) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 8) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 9) $ret = $min * mt_rand(1, 2);
            else if ($chip_times > 9) $ret = True;
            else $ret = True;
            break;
        case 'tonghua':
            if ($chip_times < 2) $ret = mt_rand(0, 3) == 0 ? $min * mt_rand(4, 4) : $min;
            else if ($chip_times == 2) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 3) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 4) $ret = $min * mt_rand(2, 2);
            else if ($chip_times == 5) $ret = $min * mt_rand(1, 2);
            else if ($chip_times > 5) $ret = True;
            else $ret = True;
            break;
        case 'shunzi':
            if ($chip_times < 2) $ret = mt_rand(0, 4) == 0 ? $min * mt_rand(4, 4) : $min;
            else if ($chip_times == 2) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 3) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 4) $ret = $min * mt_rand(2, 2);
            else if ($chip_times == 5) $ret = $min * mt_rand(1, 2);
            else if ($chip_times > 5) $ret = True;
            else $ret = True;
            break;
        case 'duizi':
            if ($chip_times == 0 && $min >= 160) $ret = mt_rand(0, 3) == 0 ? $min : False;
            else if ($chip_times == 0 && $min >= 40)   $ret = mt_rand(0, 2) == 0 ? $min : False;
            else if ($chip_times == 0) $ret = mt_rand(0, 10) == 0 ? $min * 4 : False;
            else if ($chip_times == 1) $ret = $min * mt_rand(1, 2);
            else if ($chip_times == 2) $ret = $min * mt_rand(2, 2);
            else if ($chip_times == 3) $ret = $min * mt_rand(1, 2);
            else if ($chip_times > 3) $ret = True;
            else $ret = True;
            break;
        default:
            $pc_pokers = _arrange_pokers(get_it_pokers($data, $account));
            $bigger = $pc_pokers[0] > mt_rand(10, 14) ? True : False;

            if ($all > $pc / 10) $ret = mt_rand(0, 1) == 0 ? $min : $bigger;
            if ($chip_times == 0 && $min >= 160) $ret = mt_rand(0, 3) == 0 ? $min : $bigger;
            else if ($chip_times == 0 && $min >= 40) $ret = mt_rand(0, 2) == 0 ? $min : $bigger;
            else if ($chip_times == 0) $ret = $min;
            else if ($chip_times == 1) $ret = mt_rand(0, 1) == 0 ? $min * mt_rand(1, 2) : $min;
            else if ($chip_times > 1) $ret = mt_rand(0, 5) == 0 ? $min : $bigger;
            else $ret = $bigger;
    }
    /** 修正 */
    if ($data['round']['gamer_total'] > 2){
        if ($ret === True) $ret = $min; 
        if ($chip_times > 9) $ret = mt_rand(0, 1) == 0 ? False : $min;
    }

    return $ret;
}


/**
 * 新开一局，重置赛局数据
 */
function new_round($data){
    global $config;
    $out_num = 0;
    foreach ($data['gamers'] as $k => $v){
        $data['gamers'][$k]['pokers']       = array('#', '#', '#');    
        $data['gamers'][$k]['status']       = 'playing';    
        $data['gamers'][$k]['chip_sum']     = 0;    
        $data['gamers'][$k]['chip_last']    = 0;    
        $data['gamers'][$k]['chip_times']   = 0;    
        $data['gamers'][$k]['viewed']       = $v['isbot'] ? 1 : null;    
        if (!$config['borrow'] && $v['chips'] <= 0) {
            $data['gamers'][$k]['status'] = 'ready';    
            ++$out_num;
        }
    }
    $data['round']['winner']        = '';
    $data['round']['openner']       = '';
    $data['round']['speaker']       = $data['round']['master'];
    $data['round']['show_poker']    = 0;
    $data['round']['gamer_total']   = count($data['gamers']) - $out_num;
    $data['round']['chip_sum']      = 0;
    $data['round']['chip_last']     = 5;

    $data['status'] = 'playing';
    return $data;
}

/**
 * 除玩家自己外，是否只剩机器人
 */
function only_bots($data, $account){
    $ret = True;
    foreach ($data['gamers'] as $v){
        if ($v['isbot'] || $v['account'] == $account) continue;
        else $ret = False;
    }
    return $ret;
}

/**
 * 本局结束
 *
 * @param array 
 * @param string  
 */
function finish($data, $account = Null){
    if (empty($account)){
        foreach ($data['gamers'] as $k => $v){
            if (!$v['isbot']){
                $k_of_not_bot = $k;
                if (isset($winner)) break;
            }
            if ($v['status'] == 'playing'){
                $winner = $v['account'];
                $data['gamers'][$k]['status'] = 'ready';
                $data['gamers'][$k]['chips'] += $data['round']['chip_sum'];
                $isbot = $v['isbot'];
                if (isset($k_of_not_bot)) break;
            }
        }
    } else {
        $winner = $account;  
        $isbot = 0;
    }
    $data['round']['winner'] = $winner;
    /** 如果赢家是机器人，庄家资格给房主 */
    $data['round']['master'] = $isbot ? $data['gamers'][$k_of_not_bot]['account'] : $winner;
    $data['round']['show_poker'] = 1;
    $data['round']['chip_sum'] = 0;
    $data['status'] = 'ready';
    return $data;
}

/**
 * 本局是否结束
 */
function is_finish($data, $account){
    $ret = $data['round']['gamer_total'] == 1 ? True : False;   
    return $ret;
}

/**
 * 弃牌
 */
function pass($data, $account){
    $key = find_key($data, $account);
    $data['gamers'][$key]['status'] = 'pass';
    $data['round']['gamer_total']--;
    return $data;
}

/** 过滤牌中html标签 */
function _filter($str){
    return str_replace(array('<span style="color:red">','</span>','<strong>','</strong>'), '', $str);    
}
/**
 * 得到决斗玩家的手牌（去除html）
 *
 * @return array array(
 *                  0 => array(
 *                          '1' => array(花色, 点数),
 *                          '2' => array(花色, 点数),
 *                          '3' => array(花色, 点数),
 *                          ),
 *                  3 => array(
 *                          '1' => array(花色, 点数),
 *                          '2' => array(花色, 点数),
 *                          '3' => array(花色, 点数),
 *                          ),
 *                  );
 */
function get_their_pokers($data){
    $ret = array();
    foreach ($data['gamers'] as $k => $v){
        if ($v['status'] == 'playing'){
            foreach ($v['pokers'] as $kk => $poker){
                $i = $kk + 1;
                $tmp = explode(';', _filter($poker));
                $ret[$k]["{$i}"][0] = $tmp[0].';';
                $ret[$k]["{$i}"][1] = $tmp[1];
            }
        } 
    }
    if (count($ret) != 2) die('哎呀！出bug了！');
    return $ret;
}

/**
 * 获取某玩家手牌
 */
function get_it_pokers($data, $account){
    foreach ($data['gamers'] as $v){
        if ($v['account'] == $account){
            foreach ($v['pokers'] as $kk => $poker){
                $i = $kk + 1;
                $tmp = explode(';', _filter($poker));
                if (count($tmp) == 2){
                    $ret["{$i}"][0] = $tmp[0].';';
                    $ret["{$i}"][1] = $tmp[1];
                } else {
                    $ret = False;
                    break;
                }
            }
            break;
        }   
    } 
    return $ret;
}

/**
 * 判断牌型
 */
function get_poker_style($data, $account){
    $pokers = get_it_pokers($data, $account);
    if (!$pokers) return False;

    $ret['baozi'] = baozi($pokers); 
    if (!$ret['baozi']){
        $ret['duizi'] = duizi($pokers);
    }
    $ret['tonghuashun'] = tonghuashun($pokers);
    if (!$ret['tonghuashun']){
        $ret['tonghua'] = tonghua($pokers);
        $ret['shunzi'] = shunzi($pokers);
    }
    return $ret;
}

/**
 * 比较双方大小，返回玩家以及各自牌型信息
 */
function compare($data, $account){
    $pokers_2gamers = get_their_pokers($data);
    $pokers = array();
    $i = 1;
    foreach ($pokers_2gamers as $k => $v){ //$k 为玩家键
        $pokers[$i++] = array('key' => $k, 'poker' => $v);
        if ($data['gamers'][$k]['account'] != $account) $notopenner = $k; //记录非开牌者玩家键
    }

    /** 判断牌型 */
    $g_1 = get_poker_style($data, $data['gamers'][$pokers[1]['key']]['account']);
    $g_2 = get_poker_style($data, $data['gamers'][$pokers[2]['key']]['account']);

    /** 比较大小 */
    // 豹子 
    if ($g_1['baozi'] && $g_2['baozi']){
       $winner = $g_1['baozi'][0] > $g_2['baozi'][0] ? 1 : 2;
    } else if ($g_1['baozi']){
        $winner = 1;    
    } else if ($g_2['baozi']){
        $winner = 2;    
    // 同花顺
    } else if ($g_1['tonghuashun'] && $g_2['tonghuashun']){
        if ($g_1['tonghuashun'][0] == $g_2['tonghuashun'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $g_1['tonghuashun'][0] > $g_2['tonghuashun'][0] ? 1 : 2;
        }
    } else if ($g_1['tonghuashun']){
        $winner = 1;    
    } else if ($g_2['tonghuashun']){
        $winner = 2;    
    // 同花
    } else if ($g_1['tonghua'] && $g_2['tonghua']){
        if ($g_1['tonghua'][0] == $g_2['tonghua'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $g_1['tonghua'][0] > $g_2['tonghua'][0] ? 1 : 2;
        }
    } else if ($g_1['tonghua']){
        $winner = 1;    
    } else if ($g_2['tonghua']){
        $winner = 2;    
    // 顺子
    } else if ($g_1['shunzi'] && $g_2['shunzi']){
        if ($g_1['shunzi'][0] == $g_2['shunzi'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $g_1['shunzi'][0] > $g_2['shunzi'][0] ? 1 : 2;
        }
    } else if ($g_1['shunzi']){
        $winner = 1;    
    } else if ($g_2['shunzi']){
        $winner = 2;    
    // 对子
    } else if ($g_1['duizi'] && $g_2['duizi']){
        $user_dui = $g_1['duizi'][0] == $g_1['duizi'][1] ? $g_1['duizi'][0] : $g_1['duizi'][2];
        $user_dan = $g_1['duizi'][0] == $g_1['duizi'][1] ? $g_1['duizi'][2] : $g_1['duizi'][0];
        $pc_dui = $g_2['duizi'][0] == $g_2['duizi'][1] ? $g_2['duizi'][0] : $g_2['duizi'][2];
        $pc_dan = $g_2['duizi'][0] == $g_2['duizi'][1] ? $g_2['duizi'][2] : $g_2['duizi'][0];
        //谁的对牌大
        if ($user_dui != $pc_dui){
            $winner = $user_dui > $pc_dui ? 1 : 2;
        //谁的单牌大
        } else if ($user_dan != $pc_dan){
            $winner = $user_dan > $pc_dan ? 1 : 2;
        } else {
            $winner = $notopenner; //先开者输
        }
    } else if ($g_1['duizi']){
        $winner = 1;    
    } else if ($g_2['duizi']){
        $winner = 2;    
        
    // 散牌
    } else {
        $user_pokers = _arrange_pokers($pokers[1]['poker']);
        $pc_pokers = _arrange_pokers($pokers[2]['poker']);
        if ($user_pokers[0] != $pc_pokers[0]){
            $winner = $user_pokers[0] > $pc_pokers[0] ? 1 : 2;
        } else if ($user_pokers[1] != $pc_pokers[1]) {
            $winner = $user_pokers[1] > $pc_pokers[1] ? 1 : 2;
        } else if ($user_pokers[2] != $pc_pokers[2]) {
            $winner = $user_pokers[2] > $pc_pokers[2] ? 1 : 2;
        } else {
            $winner = $notopenner; //先开者输
        }
    }
    
    $ret_winner = $data['gamers'][$pokers[$winner]['key']]['account'];
    $ret_loster = $data['gamers'][$pokers[3 - $winner]['key']]['account'];
    $ret = array(
            array('key'=>$pokers[$winner]['key'], 'account'=>$ret_winner),    //[赢] array(玩家键, 玩家帐号)
            array('key'=>$pokers[3 - $winner]['key'], 'account'=>$ret_loster),//[输] array(玩家键, 玩家帐号)
            array($g_1, $g_2) // array(玩家1牌型, 玩家2牌型)
            );
    return $ret;
}
/**
 * 判断豹子
 */
function baozi($pokers){
    $pokers = _arrange_pokers($pokers);
    if (($pokers[0] == $pokers[1]) && ($pokers[1] == $pokers[2])) {
        return $pokers;
    } else { return False; }
}
/**
 * 判断同花顺
 */
function tonghuashun($pokers){
    $tonghua = tonghua($pokers);
    $shunzi = shunzi($pokers);
    $ret = $tonghua && $shunzi ? $tonghua : False;   
    return $ret;
}
/**
 * 判断金花
 */
function tonghua($pokers){
    if (($pokers['1'][0] == $pokers['2'][0]) && ($pokers['2'][0] == $pokers['3'][0])) {
        $pokers = _arrange_pokers($pokers);
        return $pokers;
    } else { return False; }
}
/**
 * 特殊牌点数转换
 */
function _alpha2number($str){
    $str = str_replace('A', '14', $str);   
    $str = str_replace('J', '11', $str);   
    $str = str_replace('Q', '12', $str);   
    $str = str_replace('K', '13', $str);   
    return $str;
}
/**
 * 按点数排序
 */
function _arrange_pokers($pokers){
    $num[] = _alpha2number($pokers['1'][1]);
    $num[] = _alpha2number($pokers['2'][1]);
    $num[] = _alpha2number($pokers['3'][1]);
    rsort($num);
    return $num;
}
/**
 * 判断顺子
 */
function shunzi($pokers){
    $num = _arrange_pokers($pokers);
    if (($num[0]-$num[1]==1) && ($num[1]-$num[2]==1)) {
        return $num;
    } else { return False; }
}
/**
 * 判断对子
 */
function duizi($pokers){
    $pokers = _arrange_pokers($pokers);
    if (($pokers[0] == $pokers[1]) ||
        ($pokers[1] == $pokers[2]) || 
        ($pokers[2] == $pokers[0])) {
        return $pokers;
    } else { return False; }
}


