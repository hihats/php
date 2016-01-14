<?php

// sample1
$sample_str = <<< EOM
○●◎〇◯♂♀☆★◇◆□■△▲▽▼〒→←↑↓''""（）〔〕［］｛｝〈〉《》「」『』【】、。，．・：；？！´＾ヽヾゝゞ〃…‥′″仝々〆ー＋±×÷＝≠＜＞≦≧∞∴≒≡∫√⊥∠∵∩∪∈∋⊆⊇⊂⊃∧∨⇒⇔∀∃⌒∂∇≪≪≫∽∝∬ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩαβγδεζηθικλμνξοπρστυφχψω> > АБВГДЕЁЖЗТИЙКЛМНОПРСТУФХЦЧШЩЪЪЫЬЭЭЮЯабвгддеёжзийклмноппрстуфхцчшщъыьэюя┌─┐┏━┓┯┰ ├┬┤┣┳┫ ┝┿┥┠╂┨ ├┼┤┣╋┫┷┸╂￣＿―／＼｜＃＊＠§※〓♯♭♪†‡‡％℃￥＄ │││┃┃┃└┴┘┗┻┛
EOM;
// sample2
$sample_str = "｢ｶﾀｶﾅ｣♣↜↻⇄↔↖↑◧⚬●⬤⭘✝☦æaːɔːⱯ";

$str = isset($argv[1])? $argv[1] : $sample_str;

$ret = preg_match_all('/.?/u', $str, $m);
foreach ($m[0] as $s) {
    if(!mismatchByEncoding($s)) echo $s.PHP_EOL;
}
function mismatchByEncoding($string, $from_encoding='UTF-8', $to_encoding='SJIS'){
    if ( strlen($string) !== strlen(mb_convert_encoding(mb_convert_encoding($string,$to_encoding,$from_encoding),$from_encoding,$to_encoding)) ) {
        return FALSE;
    }
    // 半角カナcheck
    $regexp = '[ｦ-ﾟｰ ]';    // 半角カナ文字のみ
    //$regexp = '[｡-ﾟｰ ]';    // 半角句読点鍵括弧も含む
    if (preg_match("/$regexp+/u", $string)){
        return FALSE;
    }
    return TRUE;
}
