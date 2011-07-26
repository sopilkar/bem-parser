#!/usr/bin/php
<?php
/**
 * @author Alexander Koleda <alexander.koleda@gmail.com>
 * @version 0.01
 * @package BEM
 */
if (count($argv) < 3) {  
  print "Анализироване и создание в автоматическом режиме bemdecl.js файла.\n";
  print "Параметры: " 
  . $argv[0] . " имя_html_файла имя_bemdecl.js_файла [путь_levels]\n\n";
  print "Если путь_levels опущен, то bem-сущности создаваться не будут.\n";
  exit();
}
define('BLOCK', 1);
define('ELEM', 3);
define('MOD', 5);
define('MODVAL', 6);
// Грузим файл
$html = file_get_contents($argv[1], FILE_TEXT);
preg_match_all('/([bi]-[\w\d-]+)/i', $html, $matchesarray);
$single_arr = $matchesarray[0];
// Удаляем дубликаты
$unique_arr = array_unique($single_arr);
$pattern = '/([bi]-[A-Za-z\d-]+)(__([A-Za-z\d-]+))?(_([A-Za-z\d-]+)_([A-Za-z\d-]+))?/i';
$level = isset($argv[3]) ? $argv[3] : '';
$outarr = Array();
// Парсим каждый уникальный блок/элемент/модификатор и строим дерево-массив
foreach ($unique_arr as $cssClass) {
    preg_match($pattern, $cssClass, $bem);
    // Проверка блока
    $block = isset($bem[BLOCK]) ? $bem[BLOCK] : '';
    if ($block != '') {
        if (!isset($outarr[$block])) {
            $outarr[$block] = Array();
            bemCreate($level, $block);
        }
    }
    // Проверка элемента
    $elem = isset($bem[ELEM]) ? $bem[ELEM] : '';
    $isElement = false;
    if ($elem != '') {
        $isElement = true;
        if (!isset($outarr[$block]['elems'][$elem])) {
            $outarr[$block]['elems'][$elem] = Array();
            bemCreate($level, $block, $elem);
        }
  }
  // Проверка модификатора
  $mod = isset($bem[MOD]) ? $bem[MOD] : '';
  $modval = isset($bem[MODVAL]) ? $bem[MODVAL] : '';
  if ($mod != '' && $modval != '') {
      if ($isElement) {
          // Модификатор для элемента?      
          $outarr[$block]['elems'][$elem]['mods'][$mod][] = $modval;
          bemCreate($level, $block, $elem, $mod, $modval);
      } else {
          // Модификатор для блока?      
          $outarr[$block]['mods'][$mod][] = $modval;
          bemCreate($level, $block, null, $mod, $modval);
      }
  }
}
// Сохраняем дерево в формате bemdecl.js
export($outarr, $argv[2]);

/**
 *
 * @param type $level
 * @param type $block
 * @param type $elem
 * @param type $mod
 * @param type $modval 
 */
function bemCreate($level, $block, $elem=null, $mod=null, $modval=null) {
    if ($level == '') return;
    $command = 'bem create ';
      if (!$elem && !$mod) {
          $command .= 'block ' . $block;    
      } else if (!$mod) {
          $command .= 'elem ' . $elem . ' -b ' . $block;    
      } else if (!$elem) {
          $command .= 'mod -v ' . $modval . ' ' . $mod. ' -b ' . $block;
      } else {
          $command .= 'mod -e ' . $elem . ' -v ' . $modval . ' ' . $mod . ' -b ' . $block;
      }
      $command .= ' -l ' . $level . ' -t css';
      exec($command);  
}

/**
 *
 * @param type $arr
 * @param type $filename 
 */
function export($arr, $filename) {  
    $blocks = Array();
    foreach ($arr as $blockname => $block) {
        $blockitem = 'name: "' . $blockname . '"';    
        if (isset($block['elems'])) {      
            $elems = Array();
            foreach ($block['elems'] as $elemname => $elem) {
                $elemitem = 'name: "' . $elemname . '"';
                if (isset($elem['mods'])) {
                    $mods = Array();
                    foreach ($elem['mods'] as $modname => $mod) {
                        $mods[] = 'name: "' . $modname . '", vals: ["' . implode('", "', $mod) . '"]';
                    }
                    $elemitem .= ', mods: [{ ' . implode(' }, { ', $mods) . ' }]';
                }
                $elems[] = $elemitem;
            }
            $blockitem .= ', elems: [{ ' . implode('}, {', $elems) . ' }]';
        }
          if (isset($block['mods'])) {
              $mods = Array();
              foreach ($block['mods'] as $modname => $mod) {            
                  $mods[] = 'name: "' . $modname . '", vals: ["' . implode('", "', $mod) . '"]';
              }
              $blockitem .= ', mods: [{ ' . implode(' }, { ', $mods) . ' }]';
            }    
        $blocks[] = $blockitem;    
    }
    $result = 'exports.blocks = [' . "\n{ " . implode(" },\n{ ", $blocks) . " }\n]\n";  
    file_put_contents($filename, $result);  
}