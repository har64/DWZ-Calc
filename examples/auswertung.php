<?php
namespace har\Ligaman;

\defined('_JEXEC') or die;

use har\Ligaman\ligaman as lm;
use har\Ligaman\tabelle as tab;
use har\MyDB;
use har\Dwz;

/**
 * Klasse auswertung
 * 
 * zeigt die inoffizielle DWZ-Auswertung aller Ligen an
 * 
 * Version 1.0
 * Änderungshistorie
 * 22.11.2025 Erstellung
 */
class auswertung extends Dwz
{
  private static $players = [];
  private static $spieler = [];

  /**
   * Konstruktor
   * @param int $dwz          : aktuelle DWZ
   * @param int $index        : aktueller DWZ-Index
   * @param int $geburtsjahr  : Geburtsjahr des Spielers
   * @param array $dwz_gegner : DWZ der Gegner
   * @param float $punkte     : erzielte Punkte
   */
  public function __construct($dwz = 0, $index = 0, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
  {
    parent::__construct($dwz, $index, $geburtsjahr, $dwz_gegner, $punkte);
    MyDB::getWam()->useStyle('com_ligaman.ligen');
    lm::initVars();
    $headline = MyDB::getMenu()->title;
    $sn = lm::$SaisonName;
    echo <<<HTML
<div class='myTooltip' style='margin-top:-50px'>
  <h1>$headline <span style='font-size:20px'>Saison $sn (inoffiziell)</span></h1>
  <div class='tooltipContent'>
    Das ist nur eine vorläufige DWZ-Auswertung!
    Sie kann und soll nicht die offizielle Auswertung der Wertungsreferenten ersetzen.
    Obwohl diese Auswertung nach den Berechnungsbestimmungen der Wertungsordnung umgesetzt wurde,
    kann natürlich nicht garantiert werden, dass sie absolut korrekt ist. Insbesondere werden keine Restpartien berücksichtigt.
  </div>
</div>

HTML;
    self::getPlayers();
    self::getSpieler();
  }

  /**
   * private statische Methode getPlayers
   * 
   * holt die Spieler aus DSB-DB
   * 
   * @return void
   */
  private static function getPlayers()
  {
    MyDB::$mk_query
      ->select(['DISTINCT v.ZPS', 'v.Name'])
      ->from(lm::teams . ' AS t')
      ->innerJoin(lm::vereine . ' AS v ON v.ID = t.VereinsID')
      ->where('SaisonID = ' . lm::$AktSaisonID);
    $vereine = MyDB::loadAssocList();
    foreach ($vereine as $verein)
      if ($verein['ZPS']) {
        $players = lm::getDSBdbSpieler($verein['ZPS']);
        foreach ($players as &$player)
          $player = array_merge($player, ['Verein' => $verein['Name']]);
        self::$players = array_merge(self::$players, $players);
      }
  }

  /**
   * private statische Methode getSpieler
   * 
   * ermittelt alle Daten der Spieler
   * 
   * @return void
   */
  private static function getSpieler()
  {
    MyDB::$mk_query
      ->select('p.*')
      ->from(lm::paarungen . ' AS p')
      ->innerJoin(lm::mpaare . ' AS mp ON mp.ID = p.MP_ID')
      ->innerJoin(lm::termine . ' AS t ON t.ID = mp.TerminID')
      ->where('t.SaisonID = ' . lm::$AktSaisonID);
    $paare = MyDB::loadAssocList();
    foreach ($paare as $paar) {
      $fields = [['ErgHDWZ', 'ErgH', 'PlayerH_ID', 'PlayerA_ID'], ['ErgADWZ', 'ErgA', 'PlayerA_ID', 'PlayerH_ID']];
      foreach ($fields as $i => $field) {
        $key = array_search($paar[$field[2]], array_column(self::$spieler, 'ID'));
        if (is_bool($key)) {
          self::$spieler[] = [
            'ID' => $paar[$field[2]],
            'Punkte' => 0,
            'Gegner' => ['ID' => [], 'Punkte' => [], 'DWZ' => [], 'HA' => []]
          ];
          $key = array_key_last(self::$spieler);
        }
        $erg = $paar[$field[0]] ? $paar[$field[0]] : $paar[$field[1]];
        $erg = $erg == '½' ? 0.5 : $erg;
        if (is_numeric($erg) && !in_array($paar[$field[3]], self::$spieler[$key]['Gegner']['ID'])) {
          self::$spieler[$key]['Gegner']['Punkte'][] = $erg;
          self::$spieler[$key]['Gegner']['ID'][] = $paar[$field[3]];
          self::$spieler[$key]['Gegner']['HA'][] = $i == 0 ? 'A' : 'H';
        }
      }
    }
    foreach (self::$spieler as &$player) {
      MyDB::$mk_query
        ->select(['Name', 'ZPS', 'MNr', 'PKZ'])
        ->from(lm::player)
        ->where('ID = ' . $player['ID']);
      $player += MyDB::loadAssoc();
      $key = array_search($player['PKZ'], array_column(self::$players, 'PID'));
      $player['DWZ'] = intval(self::$players[$key]['DWZ']);
      $player['DWZ_Index'] = intval(self::$players[$key]['DWZ_Index']);
      $player['Geburtsjahr'] = intval(self::$players[$key]['Geburtsjahr']);
      $player['Verein'] = self::$players[$key]['Verein'];
    }
    foreach (self::$spieler as &$player) {
      $anz_partien = 0;
      foreach ($player['Gegner']['ID'] as $key => $gegnerID) {
        $sp_key = array_search($gegnerID, array_column(self::$spieler, 'ID'));
        $dwz = intval(self::$spieler[$sp_key]['DWZ']);
        $player['Gegner']['DWZ'][$key] = $dwz;
        if ($dwz) {
          $anz_partien++;
          $player['Punkte'] += $player['Gegner']['Punkte'][$key];
        }
      }
      if ($anz_partien) {
        self::initVars(
          $player['DWZ'],
          $player['DWZ_Index'],
          $player['Geburtsjahr'],
          $player['Gegner']['DWZ'],
          $player['Punkte']
        );
        $player += self::getErg();
      }
    }
    array_multisort(array_column(self::$spieler, 'Name'), SORT_ASC, SORT_FLAG_CASE | SORT_STRING, self::$spieler);
  }

  private static function formatDWZ($dwz)
  {
    $dwz = explode('-', $dwz);
    return lm::addSpace($dwz[0], 4) . '-' . lm::addSpace($dwz[1], 3);
  }

  /**
   * Methode showSpieler
   * 
   * zeigt eine Tabelle mit allen Spielern an
   * 
   * @return void
   */
  public static function showSpieler()
  {
    echo <<<HTML
<table>
<tr>
  <th>Name</th>
  <th>Verein</th>
  <th>akt. DWZ</th>
  <th style='text-align:center'>E</th>
  <th>Punkte</th>
  <th style='text-align:center'>We</th>
  <th>Lstg.</th>
  <th style='text-align:center'>&#8960;</th>
  <th>DWZ neu</th>
  <th style='text-align:center'>&Delta;</th>
</tr>

HTML;
    foreach (self::$spieler as $player)
      if (isset($player['DWZ_neu']) && $player['DWZ_neu']) {
        $dwz_alt = self::formatDWZ($player['DWZ_alt']);
        $index = $player['DWZ_Index'] + 1;
        $dwz_neu = self::formatDWZ("{$player['DWZ_neu']}-$index");
        $leistung = $player['Leistung'] ? $player['Leistung'] : '&nbsp;';
        $delta = $player['DWZ_neu'] - $player['DWZ'];
        $erwartung = number_format($player['Erwartung'], 3, ',');
        $punkte = number_format($player['Punkte'], 1, ',');
        echo <<<HTML
<tr>
  <td class='myTooltip'>{$player['Name']}
    <table class='tooltipContent' cellpadding='2' style='color:var(--color-dunkel)'>
    <tr>
      <th>Heimspieler</th>
      <th>DWZ</th>
      <th>&minus;</th>
      <th>Gastspieler</th>
      <th>DWZ</th>
      <th>Ergebnis</th>
    <tr>

HTML;
        foreach ($player['Gegner']['ID'] as $k => $gegnerID) {
          echo "<tr>\n";
          $key = array_search($gegnerID, array_column(self::$spieler, 'ID'));
          $pkte = 0;
          if ($player['Gegner']['HA'][$k] == 'H') {
            $pkte = ($player['Gegner']['Punkte'][$k] - 1) * -1;
            echo "      <td>" . self::$spieler[$key]['Name'] . "</td>\n";
            echo "      <td>" . self::$spieler[$key]['DWZ'] . "</td>\n";
          } else {
            $pkte = $player['Gegner']['Punkte'][$k];
            echo "      <td>" . $player['Name'] . "</td>\n";
            echo "      <td>" . $player['DWZ'] . "</td>\n";
          }
          echo "      <td>&minus;</td>\n";
          if ($player['Gegner']['HA'][$k] == 'H') {
            echo "      <td>" . $player['Name'] . "</td>\n";
            echo "      <td style='text-align:right'>" . $player['DWZ'] . "</td>\n";
          } else {
            echo "      <td>" . self::$spieler[$key]['Name'] . "</td>\n";
            echo "      <td style='text-align:right'>" . self::$spieler[$key]['DWZ'] . "</td>\n";
          }

          echo "      <td style='text-align:center'>" . tab::formatZahl([$pkte, ($pkte - 1) * -1]) . "</td>\n";
          echo "</tr>\n";
        }
        echo <<<HTML
    </table>
  </td>
  <td>{$player['Verein']}</td>
  <td style='text-align:right; font-family:monospace'>$dwz_alt</td>
  <td style='text-align:right'>{$player['Koeffizient']}</td>
  <td style='text-align:center'>$punkte / {$player['Partien']}</td>
  <td style='text-align:right; font-family:monospace'>$erwartung</td>
  <td style='text-align:right; font-family:monospace'>$leistung</td>
  <td style='text-align:right; font-family:monospace'>{$player['Durchschnitt']}</td>
  <td style='text-align:right; font-family:monospace'>$dwz_neu</td>
  <td style='text-align:right'>$delta</td>
</tr>

HTML;
      }
  }
}