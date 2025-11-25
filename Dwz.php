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
 * Version: 0.4
 * Änderungshistorie
 * 
 */
class Dwz
{
  private static int $dwz_alt;
  private static int $dwz_index;
  private static int $alters_faktor;
  private static $dwz_gegner = [];
  private static int $dwz_durchschnitt = 0;
  private static int $anz_partien = 0;
  private static float $punkte = 0.0;
  private static float $erwartung = 0.0;
  private static float $bremszuschlag = 0.0;
  private static float $beschleuingungsfaktor = 1.0;
  private static int $entwicklungskoeffizient;
  private static int $dwz_neu = 0;
  private static int $leistung = 0;
  private static $erg = [];
  private static $diff = [];

  /**
   * Konstruktor
   * 
   * @param int $dwz          : bisherige DWZ
   * @param int $index        : DWZ-Index
   * @param int $geburtsjahr  : Geburtsjahr des Spielers
   * @param array $dwz_gegner : DWZ der Gegner
   * @param float $punkte     : erzielte Punkte
   */
  public function __construct($dwz = 0, $index = 0, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
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
  public static function initVars($dwz = 0, $index = 0, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
  {
    self::$dwz_alt = $dwz;
    self::$dwz_neu = 0;
    self::$dwz_index = $index;
    $alter = $geburtsjahr ? date('Y') - $geburtsjahr : $geburtsjahr;
    self::$alters_faktor = $alter == 0 ? 15 : ($alter <= 20 ? 5 : ($alter <= 25 ? 10 : 15));
    self::$dwz_gegner = $dwz_gegner;
    self::$punkte = $punkte;
    self::$erwartung = 0;
    self::$erg = [];
    self::$beschleuingungsfaktor = 1.0;
    self::$bremszuschlag = 0;
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
        self::$dwz_index = $_POST['index'] ?? $GET['index'] ?? 0;
      }
    }
    if (!isset(self::$alters_faktor)) {
      $geburtsjahr = $_POST['gj'] ?? $_GET['gj'] ?? 0;
      $alter = $geburtsjahr ? date('Y') - $geburtsjahr : $geburtsjahr;
      self::$alters_faktor = $alter == 0 ? 15 : ($alter <= 20 ? 5 : ($alter <= 25 ? 10 : 15));
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
      if (is_array($gegner)) {
        foreach ($gegner as $opp)
          if ($opp)
            self::$dwz_gegner[] = $opp;
      } else {
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
    self::calcBremszuschlag();
    self::calcBeschleunigung();
    self::calcEntwK();
    self::calcLeistung();
    self::calcNewDWZ();
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
    $result = 0;
    $stddev = sqrt(80000);
    $dwz_diff /= $stddev;
    $approx_depth = 256;
    for ($i = 0, $k = 1; $i < $approx_depth; $i++) {
      $n = $i * 2 + 1;
      $k *= $n;
      $p = pow($dwz_diff, $n) / $k;
      if (is_nan($p) || is_infinite($p))
        break;
      $result += $p;
    }
    $result *= 1 / sqrt(2 * pi()) * exp(-pow($dwz_diff, 2) / 2);
    $result += 0.5;
    return max(0, $result);
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
   * private statische Methodde calcBremszuschlag
   * 
   * berechnet den Bremszuschlag bei DWZ < 1300
   * 
   * @return void
   */
  private static function calcBremszuschlag()
  {
    if (self::$dwz_alt < 1300 && self::$punkte < self::$erwartung)
      self::$bremszuschlag = exp((1300 - self::$dwz_alt) / 150) - 1;
  }

  /**
   * statische Methode calcBeschleunigung
   * 
   * berechnet den Beschleunigungs-Faktor für Jugendliche bis 20 Jahre
   * 
   * @return void
   */
  private static function calcBeschleunigung()
  {
    if (self::$alters_faktor == 5 && self::$punkte >= self::$erwartung) {
      $a = self::$dwz_alt / 2000;
      self::$beschleuingungsfaktor = $a >= 0.5 && $a < 1.0 ? $a : 1.0;
    }
  }

  /**
   * private statische Methode of calcEntwK
   * 
   * berechnet den Entwicklungskoeffizienten
   * 
   * @return void
   */
  private static function calcEntwK()
  {
    $grundwert = pow(self::$dwz_alt / 1000, 4) + self::$alters_faktor;
    $e = self::$beschleuingungsfaktor * $grundwert + self::$bremszuschlag;
    if ($e < 5.0)
      $e = 5.0;
    if (self::$bremszuschlag == 0) {
      $max = self::$dwz_index < 6 ? self::$dwz_index * 5.0 : 30.0;
      $e = $e > $max ? $max : $e;
    } elseif ($e > 150)
      $e = 150;
    self::$entwicklungskoeffizient = intval(round($e));
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
    if (self::$dwz_alt == 0) {
      if (self::$leistung)
        self::$dwz_neu = self::$leistung < 800 ? intval(self::$leistung / 8 + 700) : self::$leistung;
    } else
      self::$dwz_neu = intval(round(
        self::$dwz_alt + 800 * (self::$punkte - self::$erwartung) / (self::$entwicklungskoeffizient + self::$anz_partien)
      ));
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
    if (self::$anz_partien >= 5)
      if (self::$punkte == self::$anz_partien)
        self::$leistung = self::$dwz_durchschnitt + 677;
      elseif (self::$punkte == 0)
        self::$leistung = self::$dwz_durchschnitt - 677;
      else {
        if (empty(self::$diff))
          self::calcDiff();
        $p = round(self::$punkte / self::$anz_partien, 3);
        self::$leistung = self::$dwz_durchschnitt + self::getDiff($p);
        do {
          $erwartung = 0;
          foreach (self::$dwz_gegner as $gegner)
            $erwartung += self::probability(self::$leistung - $gegner);
          $p = round(0.5 + (self::$punkte - $erwartung) / self::$anz_partien, 3);
          $diff = self::getDiff($p);
          self::$leistung += $diff;
        } while ($diff);
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
      'Koeffizient' => self::$entwicklungskoeffizient,
      'Beschleunigung' => self::$beschleuingungsfaktor,
      'Bremszuschlag' => self::$bremszuschlag,
      'Durchschnitt' => self::$dwz_durchschnitt,
      'Leistung' => self::$leistung
    ];
  }

  /**
   * statische Methode getErg
   * 
   * gibt das Ergebnis zurück
   * 
   * @return array{DWZ_alt: int, DWZ_neu: int, Erwartung: float, Koeffizient: int, Partien: int}
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