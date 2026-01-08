<?php

/**
 * PHP version of DWZ calculation
 *
 * Tags: dwz php8
 *
 * @category Library
 * @package  har64\Dwz
 * @author   Harry Riegger <harry@riegger.info>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://github.com/har64/DWZ-Calc
 *
 */

namespace har64;

/**
 * Class dwz
 * 
 * Unofficial calculation of German rating number (Deutsche Wertungs-Zahl = DWZ) according to the scoring regulations of German Chess Federation
 * 
 * @category Library
 * @package  har64\Dwz
 * @author   Harry Riegger <harry@riegger.info>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * 
 */
class Dwz
{
  private static int $dwz_alt;                         # current DWZ
  private static int $dwz_index;                       # current Index of DWZ
  private static int $alters_faktor;                   # age fate: age <= 20: 5, 21 <= 25: 10, > 25: 15
  private static $dwz_gegner = [];                     # array with DWZ of opponents
  private static int $dwz_durchschnitt = 0;            # average of oppenent DWZ
  private static int $anz_partien = 0;                 # number of games evaluated
  private static float $punkte = 0.0;                  # points scored
  private static float $erwartung = 0.0;               # profit expectation
  private static float $bremszuschlag = 0.0;           # brake surcharge
  private static float $beschleuingungsfaktor = 1.0;   # acceleration factor
  private static int $entwicklungskoeffizient;         # development coefficient
  private static int $dwz_neu = 0;                     # calculated DWZ
  private static int $leistung = 0;                    # tournament performance
  private static $erg = [];                            # results
  private static $diff = [];                           # probability of a DWZ difference

  /**
   * constructor
   * 
   * @param int $dwz          : current DWZ
   * @param int $index        : current Index of DWZ
   * @param int $geburtsjahr  : year of birth of the player
   * @param array $dwz_gegner : DWZ of opponents
   * @param float $punkte     : points scored
   */
  public function __construct($dwz = 0, $index = 6, $geburtsjahr = 0, $dwz_gegner = [], $punkte = 0)
  {
    if (!empty($dwz_gegner))
      self::initVars($dwz, $index, $geburtsjahr, $dwz_gegner, $punkte);
  }

  /**
   * static method initVars
   *
   * Initialization of variables
   *
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
   * static method setParams
   * 
   * Initialization of variables by POST or GET
   *
   * Parameters
   * 'dwz'     : current DWZ
   * 'index'   : current index of DWZ
   * 'gj'      : year of birth of the player
   * 'punkte'  : points scored
   * 'gegner'  : array or semicolon separated list of DWZ of the opponents
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
   * private static method calcDWZ
   * 
   * calls all methods to calculate new DWZ
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
   * private static method fak
   * 
   * calculates recursive the faculty of an integer
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
   * private static method probability
   * 
   * calculates the expected score for a DWZ difference
   * 
   * @param int $dwz_diff : DWZ difference
   * @return float|int
   */
  private static function probability($dwz_diff)
  {
    $z = $dwz_diff / (200 * sqrt(2));
    $approx_depth = 256;
    // Calcultaion of sum
    $s = 0;
    for ($i = 0, $k = 1; $i < $approx_depth; $i++) {
      $e = 2 * $i + 1;
      $n = pow(-1, $i) * pow($z, $e);
      $d = self::fak($i) * pow(2, $i) * $e;
      $p = $n / $d;
      // Break, if accuracy of PHP is reached
      if (abs($p) < PHP_FLOAT_EPSILON || is_nan($p) || is_infinite($p))
        break;
      $s += $p;
    }
    // Calculation and return of result
    $result = 1 / sqrt(2 * pi()) * $s;
    return $result + 0.5;
  }

  /**
   * private static method calcDiff
   * 
   * calculates the DWZ difference given a probability
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
   * private static method calcErwartung
   * 
   * calculates the profit expectation
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
   * private static method calcBremszuschlag
   * 
   * calculates the brake surcharge at DWZ < 1300
   * 
   * @return void
   */
  private static function calcBremszuschlag()
  {
    if (self::$dwz_alt < 1300 && self::$punkte < self::$erwartung)
      self::$bremszuschlag = exp((1300 - self::$dwz_alt) / 150) - 1;
  }

  /**
   * private static method calcBeschleunigung
   * 
   * calculates the acceleration factor for young people up to 20 years of age
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
   * private static method of calcEntwK
   * 
   * calculates the development coefficient
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
   * private static method calcNewDWZ
   * 
   * calculates the new DWZ
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
   * private static method getDiff
   * 
   * returns the DWZ difference at a probability
   * 
   * @param float $p : probability
   * @return integer : DWZ difference
   */
  private static function getDiff($p)
  {
    while (!isset(self::$diff["$p"]))
      $p += 0.001;
    return self::$diff["$p"];
  }

  /**
   * privat static method calcLeistung
   * 
   * calculates the performance in the tournament (at least 5 games)
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
        $diff = self::getDiff($p);
        self::$leistung = self::$dwz_durchschnitt + $diff;
        while ($diff) {
          $erwartung = 0;
          foreach (self::$dwz_gegner as $gegner)
            $erwartung += self::probability(self::$leistung - $gegner);
          $p = round(0.5 + (self::$punkte - $erwartung) / self::$anz_partien, 3);
          $diff = self::getDiff($p);
          self::$leistung += $diff;
        };
      }
  }

  /**
   * private static Methode fillErg
   * 
   * fills the result array
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
   * static method getErg
   * 
   * returns the result
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
   * static method showErg
   * 
   * outputs the result in JSON e.g. for AJAX Query
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
