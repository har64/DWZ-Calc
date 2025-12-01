# DWZ Calculation
Unofficial calculation of German rating number (Deutsche Wertungs-Zahl = DWZ) according to the scoring regulations of German Chess Federation

## Requirements
* PHP 8.3 or newer

## Installing
Put Dwz.php on your webspace e.g.  $_SERVER['DOCUMENT_ROOT'] . /libraries/har64/

## Getting started
With [Autoloader](https://www.php-fig.org/psr/psr-4/)/[Composer](https://getcomposer.org):

```php
<?php
use har64\Dwz;

$dwz = new Dwz(1742, 81, 1964, [1948,1874,1586,1658,1853,1821,1697], 4.5);
$dwz::showErg();
```

Plain old PHP:

```php
<?php
  require $_SERVER['DOCUMENT_ROOT'] . '/libraries/har64/Dwz.php');

  $dwz = new har64\Dwz(1742, 81, 1964, [1948,1874,1586,1658,1853,1821,1697], 4.5);
  echo '<pre>' . print_r($dwz::getErg(), true) . '</pre>';
```

Refer to the [examples](https://github.com/har64/DWZ-Calc/tree/master/examples).

## Using for forms
The following input-fields are required
<table>
<tr>
  <th>field</th>
  <th>meaning</th>
  <th>type</th>
  <th>remark</th>
</tr>
<tr>
  <td>dwz</td>
  <td>current DWZ</td>
  <td>integer | string</td>
  <td>it's possible to hand over DWZ and index (DWZ-index)</td>
</tr>
<tr>
  <td>index</td>
  <td>current index of DWZ</td>
  <td>integer</td>
  <td>only if not part of 'dwz'</td>
</tr>
<tr>
  <td>gj</td>
  <td>year of bearth of player</td>
  <td>integer</td>
  <td></td>
</tr>
<tr>
  <td>punkte</td>
  <td>scored points</td>
  <td>float</td>
  <td>step='0.5', only against opponents with DWZ > 0</td>
</tr>
<tr>
  <td>gegner</td>
  <td>DWZ of opponents</td>
  <td>array of integer or <br>semicolon separated list</td>
  <td></td>
</tr>
</table>

## Licencse
[GPL license]: https://www.gnu.org/copyleft/gpl.html "GNU General Public License 3"
This project is open-sourced software licensed under the [GPL license]
