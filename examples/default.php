<?php
defined('_JEXEC') or die;

use har\MyDB;
use har\Dwz;

MyDB::getWam()->useStyle('com_turniere.style');
$headline = MyDB::getMenu()->title;
echo "<h1>$headline <span style='font-size:20px'>(inoffiziell)</span></h1>\n";

$year = date('Y') - 5;

$erg = [];
if (MyDB::issetInput('calc'))
  $erg = dwz::getErg();
$dwz = MyDB::getInput('dwz', 1600);
$index = MyDB::getInput('index', 5);
$gj = MyDB::getInput('gj', 1980);
$punkte = MyDB::getInput('punkte', '0.0', 'FLOAT');
$gegner = MyDB::getInput('gegner', [], 'ARRAY');
$disabled = '';
$erstDWZcheck = '';
if (MyDB::getInput('erstDWZ')) {
  $disabled = ' disabled';
  $erstDWZcheck = ' checked';
}
$erstDWZcheck = MyDB::getInput('erstDWZ') ? ' checked' : '';
echo <<<HTML
<script type='text/javascript'>
function chkGegner() {
  var anz = 0;
  const punkte = Math.round($('#punkte').val());
  $("input[name='gegner[]'").each(function() {
    if ($(this).val() != "") anz++;
  });
  if (anz < punkte) {
    alert('Bitte mindestens soviele generische DWZ wie erzielte Punkte angeben.');
    return false;
  }
  return true;
}

function resetForm() {
  $('#calcDWZ :input').each(function() {
    $(this).val('');
    if ($(this).prop('disabled')) $(this).prop('disabled', false);
  });
  $('#erstDWZ').prop('checked', false);
}

function toogleDWZ() {
  if ($('#erstDWZ').is(':checked')) {
    $('#dwz').prop('disabled', true);
    $('#index').prop('disabled', true);
  } else {
    $('#dwz').prop('disabled', false);
    $('#index').prop('disabled', false);
  }
}
</script>

<form method='POST' id='calcDWZ' onsubmit='return chkGegner();'>
<fieldset>
  <legend>eigene Daten</legend>
  <div class='full-input'>
    <label for='dwz'>Bisherige DWZ</label>
    <input type='number' name='dwz' id='dwz' max='3000' min='600' value='$dwz' required$disabled />
  </div>
  <div class='full-input'>
    <label for='index'>Index</label>
    <input type='number' name='index' id='index' max='200' min='1' value='$index' required$disabled />    
  </div>
  <div class='full-input' style='margin-left:10px'>
    <label for='gj'>Geburtsjahr</label>
    <input type='number' name='gj' id='gj' min='1910' max='$year' value='$gj' required />    
  </div>
  <div class='full-input' style='margin-left:10px'>
    <label for='punkte'>Punkte</label>
    <input type='number' name='punkte' id='punkte' min='0' max='20' step='0.5' value='$punkte' required />    
  </div>
  <div style='display:inline; margin-left:20px'>
    <input type='checkbox' name='erstDWZ' id='erstDWZ' onChange='toogleDWZ();' value='1'$erstDWZcheck />
    <label for='erstDWZ'>Erst-DWZ berechnen</label>
  </div>
</fieldset>
<fieldset>
  <legend>gegnerische DWZ</legend>

HTML;
  for ($i = 0; $i < 9; $i++) {
    if (isset($gegner[$i])) $opp = $gegner[$i];
    echo <<<HTML
    <div style='display:inline; margin-right:5px'>
      <input type='number' name='gegner[]' min='600' max='3000' value='$opp' />
    </div>

HTML;
  }
echo <<<HTML
</fieldset>
  <div style='margin:0 0 10px 10px'>
    <button type='submit' name='calc' class='btn-outline' style='margin-right:20px' >DWZ berechnen</button>
    <button type='button' class='btn-red btn-outline' onclick='resetForm();'>neue Auswertung</button>
  </div>
</form>

HTML;
if (!empty($erg)) {
  $erwartung = str_replace('.', ',', $erg['Erwartung']);
  $leistung = $erg['Leistung'] ?  "Leistung: {$erg['Leistung']}" : '';
  echo <<<HTML
<fieldset>
  <legend>Auswertung</legend>
  <div>
    neue DWZ: {$erg['DWZ_neu']}&nbsp;&nbsp;
    Gewinnerwartung:  $erwartung&nbsp;&nbsp;
    Entwicklungskoeffizient: {$erg['Koeffizient']}&nbsp;&nbsp;
    DWZ-&#8960; Gegner: {$erg['Durchschnitt']}&nbsp;&nbsp;
    $leistung
  <div>
</fieldset>

HTML;
}