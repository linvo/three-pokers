<?php
/**
 * 诈金花（PHP单机版）
 *
 * linvo@foxmail.com
 * 2011/12/15
 */
session_start();
header('Content-Type:text/html;Charset=utf-8;');
//echo '<a target="_blank" href="zjh_yange.txt">查看源码（无AI阉割版）</a>';
$shcd = array(
        'S' => '&spades;',	
        'H' => '<span style="color:red">&hearts;</span>',
        'C' => '&clubs;',
        'D' => '<span style="color:red">&diams;</span>',
        );
$num = array('1'=>'A','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10','11'=>'J','12'=>'Q','13'=>'K');
$poker_style = array(
    'baozi' => '豹子',
    'tonghuashun' => '同花顺',
    'tonghua' => '金花',
    'shunzi' => '顺子',
    'duizi' => '对子',
);
$max_chip = 1000; //每局最大下注数总额
$open_poker_chip = 2; //开牌倍数
$_SESSION['chip_now'] = isset($_SESSION['chip_now']) ? $_SESSION['chip_now'] : 5; //最小下注
$_SESSION['show_me'] = isset($_SESSION['show_me']) ? $_SESSION['show_me'] : 0; //是否看牌过
$show = 0;
$gameover = '';
///////////////////////////////////////////////////////////////////////

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$chip_now = isset($_REQUEST['chip']) ? $_REQUEST['chip'] : $_SESSION['chip_now'];
switch ($action){
    case '看牌':
        if (!in_array($_SESSION['step'], array(1, 2))) break;
        if (!$_SESSION['show_me']){ // 第一次
            $_SESSION['show_me'] = 1;
            if ($_SESSION['step'] != 1) $_SESSION['chip_now'] *= 2;
        }
        $_SESSION['step'] = 1;
        break;

    case '发牌': //发牌
        if (!in_array($_SESSION['step'], array(0, 3))) break;
        chips('user', 5);
        chips('pc', 5);
        ready_pokers();
        $_SESSION['show_me'] = 0; 
        $_SESSION['step'] = 1;
        break;

    case '下注': //加筹码
        if (!in_array($_SESSION['step'], array(1, 2))) break;
        if ($chip_now < $_SESSION['chip_now']){
            $alert = '施主，本局每注已涨到'.$_SESSION['chip_now'].'了！'; 
            break;
        }
        $pc_chip_now_x2 = $_SESSION['show_me'] == 0 ? True : False; //用户未看牌，pc需要加倍下注
        $chip_now_pc_will = $pc_chip_now_x2 ? $chip_now * 2 : $chip_now;
        if ($chip_now + $chip_now_pc_will + $_SESSION['chip_'] > $max_chip){
            $alert = '施主手下留情，本局赌注快达上限了。实在不行您就开了吧~'; 
            break;
        }
        $chip_now_user = chips('user', $chip_now) ? $chip_now : ''; //user下注

        $chip_now_pc = AI_pc_chip_now();
        if ($chip_now_pc === False) pass();
        else if ($chip_now_pc === True || ($chip_now_pc + $_SESSION['chip_']) > $max_chip) open();
        if ($pc_chip_now_x2 && ($chip_now_pc < $chip_now * 2)) $chip_now_pc = $chip_now * 2; //用户闷中
        else $pc_chip_now_x2 = False;
        $chip_now_pc = chips('pc', $chip_now_pc, $pc_chip_now_x2) ? $chip_now_pc : ''; //pc下注

        ++$_SESSION['chip_times'];
        $_SESSION['step'] = 2;
        break;
        
    case '开牌': //开牌
        if (!in_array($_SESSION['step'], array(1, 2))) break;
        $openner = isset($_REQUEST['openner']) ? 'pc' : 'user';
        $notopenner = isset($_REQUEST['openner']) ? 'user' : 'pc';
        $show = 1;
        $tmp = 'chip_now_' . $openner;
        $chip_x = $_SESSION['show_me'] == 0 && $openner == 'pc' ? 2 : 1; //用户未看牌，pc需要加倍下注

        $$tmp = chips($openner, $chip_x * $open_poker_chip * $_SESSION['chip_now'], False, True) ? $_SESSION['chip_now'] : '';
        //ready_pokers();
        list($winner, $win_info) = compare($notopenner);
        $winner_s = $winner;
        $gameover = finish($winner);
        $_SESSION['show_me'] = 1; 
        $_SESSION['chip_now'] = 5; //最小下注
        $_SESSION['chip_times'] = 0;
        $_SESSION['step'] = 3;
        break;

    case '弃牌': //放弃
        if (!in_array($_SESSION['step'], array(1, 2))) break;
        $passer = isset($_REQUEST['passer']) ? 'pc' : 'user';
        $winner = isset($_REQUEST['passer']) ? 'user' : 'pc';
        $show = 1;
        list($winner_s, $win_info) = compare();
        $gameover = finish($winner);
        $_SESSION['show_me'] = 1; 
        $_SESSION['chip_now'] = 5; //最小下注
        $_SESSION['step'] = 3;
        break;

    default: //初始化
        //if (isset($_SESSION['step']) && $_SESSION['step'] != 3) die($_SESSION['step']);
        init_game();
}

echo '<ul>';
echo '<li>Your chips:<strong>' . $_SESSION['user_chip_own'].'</strong></li>';
if ($_SESSION['show_me']){
    echo '<li>Your pokers:' . 
        '<span style="font-size:25px">[' . $_SESSION['user_poker_1'] . ']' .
        '[' . $_SESSION['user_poker_2'] . ']' . 
        '[' . $_SESSION['user_poker_3'] . ']</span></li>'; 
} else {
    echo '<li>Your pokers: <span style="font-size:25px">[#] [#] [#]</span></li>';
}

echo '<br /><br />';
echo '<li>desktop chips:'.$_SESSION['chip_'].'</li>';
echo '<li>Your desktop chips:'.$_SESSION['user_chip_'];
if (isset($chip_now_user)) echo '<span style="color:green"> (+'.$chip_now_user.')</span></li>';
echo '<li>PC desktop chips:'.$_SESSION['pc_chip_'];
if (isset($chip_now_pc)) echo '<span style="color:green"> (+'.$chip_now_pc.')</span></li>';
echo '<br /><br />';

echo '<li>PC chips:<strong>' . $_SESSION['pc_chip_own'].'</strong></li>';
if ($show){
    echo '<li>PC pokers:' . 
        '<span style="font-size:25px">[' . $_SESSION['pc_poker_1'] . ']' .
        '[' . $_SESSION['pc_poker_2'] . ']' . 
        '[' . $_SESSION['pc_poker_3'] . ']</span></li>'; 
} else {
    echo  '<li>PC pokers: <span style="font-size:25px">[#] [#] [#]</span></li>';
}
echo '</ul>';

echo '<div>';
echo '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
echo '<input type="submit" name="action" value="初始化" /> | ';

$disabled = !in_array($_SESSION['step'], array(0, 3)) ? 'disabled="disabled"' : '';
echo '<input type="submit" name="action" value="发牌" '.$disabled.' />';

$disabled = !in_array($_SESSION['step'], array(1, 2)) ? 'disabled="disabled"' : '';
echo '<input type="submit" name="action" value="看牌" '.$disabled.' /> | ';

$disabled = !in_array($_SESSION['step'], array(1, 2)) ? 'disabled="disabled"' : '';
echo '<input type="submit" name="action" value="下注" '.$disabled.'/><input type="text" size="1" maxlength="3" name="chip" value="'.$_SESSION['chip_now'].'"> | ';
echo '<input type="submit" name="action" value="开牌" '.$disabled.'/>';
echo '<input type="submit" name="action" value="弃牌" '.$disabled.'/>';
echo '</form>';
echo '</div>';

echo '<hr>';

if (isset($winner)) echo '<span style="color:red"><strong>Winner:'.strtoupper($winner).'</strong></span> ';
if (isset($winner_s)) echo '<span>(Bigger:'.strtoupper($winner_s).')</span>';
if (isset($openner)) echo '<span>('.strtoupper($openner).'开牌)</span>';
if (isset($passer)) echo '<span>('.strtoupper($passer).'弃牌)</span>';
if (isset($win_info)){
    foreach ($win_info[0] as $k => $v){
        if ($v) echo '<h4 style="color:#a36">你的牌型:' . $poker_style[$k] . '</h4>';
    }
    foreach ($win_info[1] as $k => $v){
        if ($v) echo '<h4 style="color:#a36">电脑牌型:' . $poker_style[$k] . '</h4>';
    }
}

if ($gameover) {
    $alert = $gameover;
    init_game();
}
if (isset($alert) && !empty($alert)){
    echo '<h2>' . $alert . '</h2>';    
}

///////////////////////////////////////////////////////////////////////////////
//函数开始
///////////////////////////////////////////////////////////////////////////////

/**
 * 初始化游戏
 */
function init_game(){
    $_SESSION['user_chip_own'] = 1000; //用户账户余额
    $_SESSION['user_chip_'] = 0; //用户本局总下注

    $_SESSION['pc_chip_own'] = 1000; //PC账户余额
    $_SESSION['pc_chip_'] = 0; //PC本局总下注

    $_SESSION['chip_'] = 0; //本局下注总额

    $_SESSION['show_me'] = 0; //看牌
    $_SESSION['chip_now'] = 5; //最小下注
    $_SESSION['chip_times'] = 0; //本局下注次数
    $_SESSION['user_poker_1'] = $_SESSION['user_poker_2'] = $_SESSION['user_poker_3'] = $_SESSION['pc_poker_1'] = $_SESSION['pc_poker_2'] = $_SESSION['pc_poker_3'] = '';

    $_SESSION['step'] = 0;
}


function yes_or_no(){
    return mt_rand(0, 1);    
}

/**
 * 计算电脑下注数
 *
 * @return True:开牌 | False:弃牌 | int:下注
 */
function AI_pc_chip_now(){
    $min = $_SESSION['chip_now'];
    $all = $_SESSION['chip_'];
    $pc = $_SESSION['pc_chip_own'];

    $chip_times = $_SESSION['chip_times'];
    list($winner, $win_info) = compare();
    $paixing = '';
    foreach ($win_info[1] as $k => $v){
        if ($v) $paixing = $k;    
    }
    switch ($paixing){
        case 'baozi':
        case 'tonghuashun':
            #if ($all > $pc / 10) $ret = yes_or_no() ? $min : True;
            if ($chip_times == 0) $ret = mt_rand($min, $min + $chip_times);
            else if ($chip_times == 1) $ret = yes_or_no() ? $min * mt_rand(2, 2) : $min;
            else if ($chip_times == 2) $ret = yes_or_no() ? $min * mt_rand(2, 3) : $min;
            else if ($chip_times == 3) $ret = yes_or_no() ? $min * mt_rand(2, 4) : $min;
            else if ($chip_times == 4) $ret = yes_or_no() ? $min * mt_rand(2, 5) : $min;
            else if ($chip_times == 5) $ret = yes_or_no() ? $min * mt_rand(2, 6) : $min;
            else if ($chip_times == 6) $ret = yes_or_no() ? $min * mt_rand(2, 7) : $min;
            else if ($chip_times == 7) $ret = yes_or_no() ? $min * mt_rand(2, 8) : $min;
            else if ($chip_times == 8) $ret = yes_or_no() ? $min * mt_rand(2, 9) : $min;
            else if ($chip_times == 9) $ret = yes_or_no() ? $min * mt_rand(2, 10) : $min;
            else if ($chip_times > 9) $ret = True;
            else $ret = True;
            break;
        case 'tonghua':
            #if ($all > $pc / 10) $ret = yes_or_no() ? $min : True;
            if ($chip_times < 2) $ret = mt_rand($min, $min + $chip_times);
            else if ($chip_times == 2) $ret = yes_or_no() ? $min * mt_rand(2, 2) : $min;
            else if ($chip_times == 3) $ret = yes_or_no() ? $min * mt_rand(2, 3) : $min;
            else if ($chip_times == 4) $ret = yes_or_no() ? $min * mt_rand(2, 4) : $min;
            else if ($chip_times == 5) $ret = yes_or_no() ? $min * mt_rand(2, 5) : $min;
            else if ($chip_times > 5) $ret = True;
            else $ret = True;
            break;
        case 'shunzi':
            #if ($all > $pc / 10) $ret = yes_or_no() ? $min : True;
            if ($chip_times < 2) $ret = mt_rand($min, $min + $chip_times);
            else if ($chip_times == 2) $ret = yes_or_no() ? $min * mt_rand(1, 2) : $min;
            else if ($chip_times == 3) $ret = yes_or_no() ? $min * mt_rand(1, 3) : $min;
            else if ($chip_times == 4) $ret = yes_or_no() ? $min * mt_rand(1, 4) : $min;
            else if ($chip_times == 5) $ret = yes_or_no() ? $min * mt_rand(1, 5) : $min;
            else if ($chip_times > 5) $ret = True;
            else $ret = True;
            break;
        case 'duizi':
            #if ($all > $pc / 10) $ret = yes_or_no() ? $min : True;
            if ($chip_times == 0 && $min/5 >= 10)       $ret = mt_rand(0, 3) == 0 ? $min : False;
            else if ($chip_times == 0 && $min/5 >= 3)   $ret = mt_rand(0, 2) == 0 ? $min : False;
            else if ($chip_times == 0) $ret = yes_or_no() ? $min * mt_rand(1, 1) : $min;
            else if ($chip_times == 1) $ret = yes_or_no() ? $min * mt_rand(1, 2) : $min;
            else if ($chip_times == 2) $ret = yes_or_no() ? $min * mt_rand(1, 3) : $min;
            else if ($chip_times == 3) $ret = yes_or_no() ? $min * mt_rand(1, 4) : $min;
            else if ($chip_times > 3) $ret = True;
            else $ret = True;
            break;
        default:
            $pc_pokers = _arrange_pokers(get_pokers('pc'));
            $bigger = $pc_pokers[0] > mt_rand(10, 14) ? True : False;

            if ($all > $pc / 10) $ret = yes_or_no() ? $min : $bigger;
            if ($chip_times == 0 && $min/5 >= 10)       $ret = mt_rand(0, 3) == 0 ? $min : $bigger;
            else if ($chip_times == 0 && $min/5 >= 3)   $ret = mt_rand(0, 2) == 0 ? $min : $bigger;
            else if ($chip_times == 0) $ret = yes_or_no() ? $min * mt_rand(1, 1) : $min;
            else if ($chip_times == 1) $ret = yes_or_no() ? $min * mt_rand(1, 2) : $min;
            else if ($chip_times == 2) $ret = yes_or_no() ? $min * mt_rand(1, 3) : $min;
            else if ($chip_times > 2) $ret = $bigger;
            else $ret = $bigger;
    }
    //debug($paixing);

    return $ret;
}


function _filter($str){
    return str_replace(array('<span style="color:red">','</span>','<strong>','</strong>'), '', $str);    
}
/**
 * 得到某人的手牌（去除html）
 */
function get_pokers($who){
    $tmp = explode(';', _filter($_SESSION[$who.'_poker_1']));
    $ret[$who]['poker']['1'][0] = $tmp[0].';';
    $ret[$who]['poker']['1'][1] = $tmp[1];
    $tmp = explode(';', _filter($_SESSION[$who.'_poker_2']));
    $ret[$who]['poker']['2'][0] = $tmp[0].';';
    $ret[$who]['poker']['2'][1] = $tmp[1];
    $tmp = explode(';', _filter($_SESSION[$who.'_poker_3']));
    $ret[$who]['poker']['3'][0] = $tmp[0].';';
    $ret[$who]['poker']['3'][1] = $tmp[1];
    return $ret[$who]['poker'];
}

/**
 * 比较双方大小，返回winner以及各自牌型信息
 */
function compare($notopenner = ''){
    $ret['user']['poker'] = get_pokers('user');
    $ret['pc']['poker'] = get_pokers('pc');

    // 开始判断牌型
    // user
    $user['baozi'] = baozi($ret['user']['poker']);
    if (!$user['baozi']){
        $user['duizi'] = duizi($ret['user']['poker']);
    }
    $user['tonghuashun'] = tonghuashun($ret['user']['poker']);
    if (!$user['tonghuashun']){
        $user['tonghua'] = tonghua($ret['user']['poker']);
        $user['shunzi'] = shunzi($ret['user']['poker']);
    }
    // pc
    $pc['baozi'] = baozi($ret['pc']['poker']);
    if (!$pc['baozi']){
        $pc['duizi'] = duizi($ret['pc']['poker']);
    }
    $pc['tonghuashun'] = tonghuashun($ret['pc']['poker']);
    if (!$pc['tonghuashun']){
        $pc['tonghua'] = tonghua($ret['pc']['poker']);
        $pc['shunzi'] = shunzi($ret['pc']['poker']);
    }
    
    // 开始比较大小
    // 豹子 
    if ($user['baozi'] && $pc['baozi']){
       $winner = $user['baozi'][0] > $pc['baozi'][0] ? 'user' : 'pc';
    } else if ($user['baozi']){
        $winner = 'user';    
    } else if ($pc['baozi']){
        $winner = 'pc';    
    // 同花顺
    } else if ($user['tonghuashun'] && $pc['tonghuashun']){
        if ($user['tonghuashun'][0] == $pc['tonghuashun'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $user['tonghuashun'][0] > $pc['tonghuashun'][0] ? 'user' : 'pc';
        }
    } else if ($user['tonghuashun']){
        $winner = 'user';    
    } else if ($pc['tonghuashun']){
        $winner = 'pc';    
    // 同花
    } else if ($user['tonghua'] && $pc['tonghua']){
        if ($user['tonghua'][0] == $pc['tonghua'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $user['tonghua'][0] > $pc['tonghua'][0] ? 'user' : 'pc';
        }
    } else if ($user['tonghua']){
        $winner = 'user';    
    } else if ($pc['tonghua']){
        $winner = 'pc';    
    // 顺子
    } else if ($user['shunzi'] && $pc['shunzi']){
        if ($user['shunzi'][0] == $pc['shunzi'][0]){
            $winner = $notopenner; //先开者输
        } else {
            $winner = $user['shunzi'][0] > $pc['shunzi'][0] ? 'user' : 'pc';
        }
    } else if ($user['shunzi']){
        $winner = 'user';    
    } else if ($pc['shunzi']){
        $winner = 'pc';    
    // 对子
    } else if ($user['duizi'] && $pc['duizi']){
        $user_dui = $user['duizi'][0] == $user['duizi'][1] ? $user['duizi'][0] : $user['duizi'][2];
        $user_dan = $user['duizi'][0] == $user['duizi'][1] ? $user['duizi'][2] : $user['duizi'][0];
        $pc_dui = $pc['duizi'][0] == $pc['duizi'][1] ? $pc['duizi'][0] : $pc['duizi'][2];
        $pc_dan = $pc['duizi'][0] == $pc['duizi'][1] ? $pc['duizi'][2] : $pc['duizi'][0];
        //谁的对牌大
        if ($user_dui != $pc_dui){
            $winner = $user_dui > $pc_dui ? 'user' : 'pc';
        //谁的单牌大
        } else if ($user_dan != $pc_dan){
            $winner = $user_dan > $pc_dan ? 'user' : 'pc';
        } else {
            $winner = $notopenner; //先开者输
        }
    } else if ($user['duizi']){
        $winner = 'user';    
    } else if ($pc['duizi']){
        $winner = 'pc';    
        
    // 散牌
    } else {
        $user_pokers = _arrange_pokers($ret['user']['poker']);
        $pc_pokers = _arrange_pokers($ret['pc']['poker']);
        if ($user_pokers[0] != $pc_pokers[0]){
            $winner = $user_pokers[0] > $pc_pokers[0] ? 'user' : 'pc';
        } else if ($user_pokers[1] != $pc_pokers[1]) {
            $winner = $user_pokers[1] > $pc_pokers[1] ? 'user' : 'pc';
        } else if ($user_pokers[2] != $pc_pokers[2]) {
            $winner = $user_pokers[2] > $pc_pokers[2] ? 'user' : 'pc';
        } else {
            $winner = $notopenner; //先开者输
        }
    }

    return array($winner, array($user, $pc));
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


/**
 * 本局结束结算分数
 */
function finish($winner){
    $_SESSION[$winner . '_chip_own'] += $_SESSION['chip_'];
    $_SESSION['chip_'] = $_SESSION['user_chip_'] = $_SESSION['pc_chip_'] = 0;

    $ret = '';
    if ($_SESSION['user_chip_own'] <= 0) $ret = '施主，放下扑克，立地成佛~';  
    if ($_SESSION['pc_chip_own'] <= 0) $ret = '施主，扑克穿肠过，金花心中留~';  
    return $ret;
}

/**
 * 下注
 *
 * @param string 下注者
 * @param int 下注数
 * @param bool pc是否需要双倍下注（user未看牌）
 * @param bool 是否是开牌（开牌则不判断是否达本局上限）
 */
function chips($who, $chip, $shuangbei = False, $finish = False){
    global $max_chip;
    $_SESSION['chip_'] += $chip;
    if (!$finish && $_SESSION['chip_'] > $max_chip) {
        $_SESSION['chip_'] -= $chip;
        return False;
    }
    $_SESSION[$who . '_chip_own'] -= $chip;
    $_SESSION[$who . '_chip_'] += $chip;
    $_SESSION['chip_now'] = $shuangbei ? $chip / 2 : $chip;
    return True;
}

/** 
 * 洗牌&发牌
 */
function ready_pokers(){
    global $shcd, $num;

    $pokers = array();
    $flag = mt_rand(0, 1);
    $i = $j = 1;
    $_SESSION['user_poker_1'] = $_SESSION['user_poker_2'] = $_SESSION['user_poker_3'] =  $_SESSION['pc_poker_1'] = $_SESSION['pc_poker_2'] = $_SESSION['pc_poker_3'] = '';
    do{
        shuffle($shcd);
        $shcd_k = array_rand($shcd);
        shuffle($num);
        $num_k = array_rand($num);
        $poker = $shcd[$shcd_k].'<strong>'.$num[$num_k].'</strong>';
        $pokers = array(
                $_SESSION['user_poker_1'],
                $_SESSION['user_poker_2'],
                $_SESSION['user_poker_3'],
                $_SESSION['pc_poker_1'],
                $_SESSION['pc_poker_2'],
                $_SESSION['pc_poker_3'],
                );
        if (in_array($poker, $pokers)) continue;
        $who = $flag % 2 ? 'user' : 'pc'; 
        ++$flag;
        $_SESSION[$who.'_poker_'.$j] = $poker;
        if (!empty($_SESSION['user_poker_'.$j]) && !empty($_SESSION['pc_poker_'.$j])) ++$j;
        ++$i;
    }while($i < 7);
}

function debug($var){
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}
/**
 * 电脑开牌
 */
function open(){
    header('Location:'.$_SERVER['PHP_SELF'] . '?openner=pc&action=开牌');
    exit;
}
/**
 * 电脑弃牌
 */
function pass(){
    header('Location:'.$_SERVER['PHP_SELF'] . '?passer=pc&action=弃牌');
    exit;
}

