<?php
namespace har;

/**
 * Klasse dwz
 * 
 * berechnet die neue DWZ nach einem Turnier
 * 
 * Autor: Harry Riegger (harry@riegger.info)
 * Lizenz: http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * Link: https://github.com/har64/DWZ-Calc
 * 
 * Version: 0.7
 * Änderungshistorie
 * 08.01.26 Korrektur calcLeistung
 * 04.02.26 Fehlerkorrekturen
 * 03.03.26 keine Leistung, wenn DWZ = 0 und Extremresultate
 * 01.08.26 neue Wertungsordnung 
 */
class Dwz
{
  private static int $dwz_alt;
  private static int $dwz_index;
  private static int $alter;
  private static $dwz_gegner = [];
  private static int $dwz_durchschnitt = 0;
  private static int $anz_partien = 0;
  private static float $punkte = 0.0;
  private static float $erwartung = 0.0;
  private static float $bremsfaktor = 1.0;
  private static float $erfolgsaufschlag = 0.0;
  private static float $entwicklungsfaktor;
  private static int $dwz_neu = 0;
  private static int $leistung = 0;
  private static $erg = [];
  private static $diff = [];
  private const contVars = [
    'r' => 0.08,    # Korrektur-Faktor bei Erst DWZ
    't' => 30,      # Divisor bei Jugendaufschlag a
    'u' => 800,     # Verschiebung beim Bremsfaktor b
    'a1' => 4.0,    # Erfolgsaufschlag Junioren
    'a2' => 4.0,    # Erfolgsaufschlag Erwachsene
    'Kmax' => 80    # Höchstwert des K-Faktors
  ];

  /**
   * Konstruktor
   * 
   * @param int $dwz          : bisherige DWZ
   * @param int $index        : DWZ-Index
   * @param int $geburtsjahr  : Geburtsjahr des Spielers
   * @param array $dwz_gegner : DWZ der Gegner
   * @param float $punkte     : erzielte Punkte
   */
  public function __construct($dwz = 0, $index = 6, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
  {
    if (!empty($dwz_gegner))
      self::initVars($dwz, $index, $geburtsjahr, $dwz_gegner, $punkte);
  }

  /**
   * Summary of initVars
   * @param int $dwz
   * @param int $index
   * @param int $geburtsjahr
   * @param array $dwz_gegner
   * @param int $punkte
   * @return void
   */
  public static function initVars($dwz = 0, $index = 6, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
  {
    self::$dwz_alt = $dwz;
    self::$dwz_neu = 0;
    self::$dwz_index = $index;
    self::$alter = $geburtsjahr ? date('Y') - $geburtsjahr : 26;
    self::$dwz_gegner = array_filter($dwz_gegner);
    self::$punkte = $punkte;
    self::$erwartung = 0;
    self::$erg = [];
    self::$leistung = 0;
    self::calcDWZ();
  }

  /**
   * statische Methode setParams
   * 
   * füllt alle notwendigen Variablen
   * 
   * @return void
   */
  public static function setParams()
  {
    if (!isset(self::$dwz_alt)) {
      $dwz_alt = $_POST['dwz'] ?? $_GET['dwz'] ?? 0;
      $dwz = explode('-', $dwz_alt);
      if (count($dwz) > 1) {
        self::$dwz_alt = intval($dwz[0]);
        self::$dwz_index = intval($dwz[1]);
      } else {
        self::$dwz_alt = intval($dwz_alt);
        self::$dwz_index = $_POST['index'] ?? $GET['index'] ?? 6;
      }
    }
    if (!isset(self::$alter)) {
      $geburtsjahr = $_POST['gj'] ?? $_GET['gj'] ?? 0;
      self::$alter = $geburtsjahr ? date('Y') - $geburtsjahr : 26;
    }
    if (!self::$punkte) {
      $punkte = $_POST['punkte'] ?? 0;
      if (is_array($punkte)) {
        foreach ($punkte as $point)
          self::$punkte += $point;
      } else
        self::$punkte = $_POST['punkte'] ?? $_GET['punkte'] ?? 0.0;
    }
    if (empty(self::$dwz_gegner)) {
      $gegner = $_POST['gegner'] ?? 0;
      if (is_array($gegner))
        self::$dwz_gegner = array_filter($gegner);
      else {
        $gegner = $_POST['gegner'] ?? $_GET['gegner'] ?? 0;
        $dwz_opps = explode(';', $gegner);
        foreach ($dwz_opps as $opp)
          if ($opp)
            self::$dwz_gegner[] = $opp;
      }
    }
    self::calcDWZ();
  }

  /**
   * private statische Methode calcDWZ
   * 
   * berechnet die neue DWZ
   * 
   * @return void
   */
  private static function calcDWZ()
  {
    self::calcErwartung();
    self::calcBremsfaktor();
    self::calcErfolgsaufschlag();
    self::calcEntwFaktor();
    self::calcLeistung();
    self::calcNewDWZ();
  }

  /**
   * private statische Methode fak
   * 
   * brechnet die Fakultät einer Zahl
   * 
   * @param int $n
   * @return int
   */
  private static function fak($n)
  {
    $fak = ($n == 0) ? 1 : $n * self::fak($n - 1);
    return $fak;
  }

  /**
   * private statische Methode probability
   * 
   * berechnet die erwartete Punktzahl bei einer DWZ-Differenz
   * 
   * @param mixed $dwz_diff
   * @return float|int
   */
  private static function probability($dwz_diff)
  {
    $z = $dwz_diff / (200 * sqrt(2));
    $approx_depth = 256;
    // Berechnung der Summe
    $s = 0;
    for ($i = 0; $i < $approx_depth; $i++) {
      $e = 2 * $i + 1;
      $n = pow(-1, $i) * pow($z, $e);
      $d = self::fak($i) * pow(2, $i) * $e;
      $p = $n / $d;
      if (abs($p) < PHP_FLOAT_EPSILON || is_nan($p) || is_infinite($p))
        break;
      $s += $p;
    }
    // Berechnung des Ergebnisses
    $result = 1 / sqrt(2 * pi()) * $s;
    return $result + 0.5;
  }

  /**
   * private statische Methode calcDiff
   * 
   * berechnet die DWZ-Differenz bei einer Wahrscheinlichkeit
   * 
   * @return void
   */
  private static function calcDiff()
  {
    self::$diff = [1.0 => 728, 0.0 => '-728'];
    for ($p = -727; $p < 728; $p++) {
      $w = round(self::probability($p), 3);
      if (!isset(self::$diff["$w"]))
        self::$diff["$w"] = $p;
    }
  }

  /**
   * private statische Methode calcErwartung
   * 
   * berechnet die Gewinnerwartung
   * 
   * @return void
   */
  private static function calcErwartung()
  {
    $dwz_summe = 0;
    self::$anz_partien = 0;
    foreach (self::$dwz_gegner as $gegner) {
      self::$anz_partien++;
      self::$erwartung += self::probability(self::$dwz_alt - $gegner);
      $dwz_summe += $gegner;
    }
    if (self::$anz_partien)
      self::$dwz_durchschnitt = intval(round($dwz_summe / self::$anz_partien));
  }

  /**
   * private statische Methode calcBremsfaktor
   * 
   * berechnet den Bremszuschlag bei DWZ < 1600
   * 
   * @return void
   */
  private static function calcBremsfaktor()
  {
    if (self::$dwz_alt < 1600 && self::$punkte < self::$erwartung)
      self::$bremsfaktor = (self::$dwz_alt + self::contVars['u']) / (1600 + self::contVars['u']);
  }

  /**
   * statische Methode calcErfolgsaufschlag
   * 
   * berechnet den Erfolgsaufschlag
   * 
   * @return void
   */
  private static function calcErfolgsaufschlag()
  {
    if (self::$punkte >= self::$erwartung) {
      if (self::$alter < 21) {
        if (self::$dwz_alt < 2000)
          self::$erfolgsaufschlag = (2000 - self::$dwz_alt) / self::contVars['t'];
      } elseif (self::$dwz_alt < 1600)
        self::$erfolgsaufschlag = self::$alter < 26 ? self::contVars['a1'] : self::contVars['a2'];
    }
  }

  /**
   * private statische Methode of calcEntwFaktor
   * 
   * berechnet den Entwicklungsfaktor K
   * 
   * @return void
   */
  private static function calcEntwFaktor()
  {
    $k0 = [[0, 0, 0], [60, 60, 60], [60, 60, 60], [48, 44, 41], [46, 42, 39], [44, 40, 37], [42, 38, 35], [40, 36, 33], [38, 34, 31], [36, 32, 29], [34, 30, 27], [32, 28, 25]];
    $i = 2;
    if (self::$alter < 26 && self::$dwz_alt < 2200)
      $i = 0;
    elseif (self::$alter > 25 && self::$dwz_alt < 2000)
      $i = 1;
    $col = array_column($k0, $i);
    $grundwert = self::$dwz_index > 10 ? $col[11] : $col[self::$dwz_index];
    $ef = round($grundwert * self::$bremsfaktor + self::$erfolgsaufschlag, 1);
    if ($ef > self::contVars['Kmax'])
      $ef = self::contVars['Kmax'];
    self::$entwicklungsfaktor = $ef;
  }

  /**
   * statische Methode calcNewDWZ
   * 
   * berechnet die neue DWZ
   * 
   * @return void
   */
  private static function calcNewDWZ()
  {
    // Erst-DWZ
    if (self::$dwz_alt == 0) {
      if (self::$leistung) {
        if (self::$leistung >= 2000)
          self::$dwz_neu = self::$leistung;
        elseif (self::$leistung >= 1100)
          self::$dwz_neu = self::$leistung + self::contVars['r'] * (2000 - self::$leistung);
        else
          self::$dwz_neu = 1100 + 9 * self::contVars['r'] * self::$leistung / 11;
      }
    } else
      self::$dwz_neu = intval(round(self::$dwz_alt + self::$entwicklungsfaktor * (self::$punkte - self::$erwartung)));
    if (self::$dwz_neu < 1100)
      self::$dwz_neu = 1100;
  }

  /**
   * private statische Methode getDiff
   * 
   * gibt die DWZ-Differenz bei einer Wahrscheinlichkeit zurück
   * 
   * @param float $p
   * @return integer
   */
  private static function getDiff($p)
  {
    while (!isset(self::$diff["$p"]))
      $p += 0.001;
    return self::$diff["$p"];
  }

  /**
   * private statische Methode calcLeistung
   * 
   * berechnet die Leistung im Turnier
   * 
   * @return void
   */
  private static function calcLeistung()
  {
    if (self::$anz_partien >= 5) {
      if (self::$dwz_alt == 0)
        // bei 100% hinzufügen fiktives Remis nach 3.6.2
        if (self::$punkte == self::$anz_partien) {
          self::$punkte += 0.5;
          self::$anz_partien++;
          self::$dwz_gegner[] = self::$dwz_durchschnitt;
        } elseif (self::$punkte == 0)
          return;
      if (self::$punkte == 0)
        self::$leistung = 0;
      else {
        if (empty(self::$diff))
          self::calcDiff();
        $p = round(self::$punkte / self::$anz_partien, 3);
        $diff = self::getDiff($p);
        self::$leistung = self::$dwz_durchschnitt + $diff;
        while ($diff) {
          $erwartung = 0;
          foreach (self::$dwz_gegner as $gegner)
            $erwartung += self::probability(self::$leistung - $gegner);
          $p = round(0.5 + (self::$punkte - $erwartung) / self::$anz_partien, 3);
          $ndiff = self::getDiff($p);
          if ($ndiff + $diff == 0)
            break;
          else
            $diff = $ndiff;
          self::$leistung += $diff;
        }
      }
    }
  }

  /**
   * private statische Methode fillErg
   * 
   * befüllt das Ergebnis-Array
   * 
   * @return void
   */
  private static function fillErg()
  {
    if (self::$dwz_neu == 0)
      self::setParams();
    self::$erg = [
      'DWZ_alt' => self::$dwz_alt . '-' . self::$dwz_index,
      'DWZ_neu' => self::$dwz_neu,
      'Erwartung' => round(self::$erwartung, 3),
      'Partien' => self::$anz_partien,
      'Entwicklungsfaktor' => self::$entwicklungsfaktor,
      'Erfolgsaufschlag' => self::$erfolgsaufschlag,
      'Bremsfaktor' => self::$bremsfaktor,
      'Durchschnitt' => self::$dwz_durchschnitt,
      'Leistung' => self::$leistung
    ];
  }

  /**
   * statische Methode getErg
   * 
   * gibt das Ergebnis zurück
   * 
   * @return array {DWZ_alt: int, DWZ_neu: int, Erwartung: float, Koeffizient: int, Partien: int}
   */
  public static function getErg()
  {
    if (empty(self::$erg))
      self::fillErg();
    return self::$erg;
  }

  /**
   * statische Methode showErg
   * 
   * gibt das Ergebnis in JSON aus
   * 
   * @return void
   */
  public static function showErg()
  {
    if (empty(self::$erg))
      self::fillErg();
    echo json_encode(self::$erg);
  }
}