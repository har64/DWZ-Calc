<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" class="j5" xml:lang="de-de" lang="de-de" dir="ltr">

<head>
  <meta charset="utf-8">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
    .full-input {
      display: inline-block;
      padding: 3px;
      border: 2px solid blue;
      border-radius: 5px;
    }
    div.full-input > input {
      outline: none;
      border: none;
      line-height: 1.2em;
      font-size: 16px;
      background-color: white;
    }
    div.full-input > label {
      display: block;
      font-size: 14px;
      font-weight: bold;
      color: blue;
    }
  </style>
</head>
<body>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/libraries/har64/Dwz.php';

use har64\Dwz;

echo "<h1>DWZ evaluation <span style='font-size:20px'>(inoffiziell)</span></h1>\n";

$year = date('Y') - 5;

$erg = [];
if (isset($_POST['calc']))
  $erg = dwz::getErg();
$dwz = $_POST['dwz'] ?? 1600;
$index = $_POST['index'] ?? 5;
$gj = $_POST['gj'] ?? 1980;
$punkte = $_POST['punkte'] ?? '0.0';
$gegner = $_POST['gegner'] ?? [];
$disabled = '';
$erstDWZcheck = '';
if (isset($_POST['erstDWZ'])) {
  $disabled = ' disabled';
  $erstDWZcheck = ' checked';
}
$erstDWZcheck = isset($_POST['erstDWZ']) ? ' checked' : '';
echo <<<HTML
<script type='text/javascript'>
function chkGegner() {
  var anz = 0;
  const punkte = Math.round($('#punkte').val());
  $("input[name='gegner[]'").each(function() {
    if ($(this).val() != "") anz++;
  });
  if (anz < punkte) {
    alert('Please enter at least as many generic DWZ as points scored.');
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
?>
</body>
</html>
