# DWZ Calculation
Unofficial calculation of German rating number (Deutsche Wertungs-Zahl = DWZ) according to the scoring regulations of German Chess Federation

## Requirements
* PHP 8.3.28 or newer

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

## Licencse
[GPL license]: https://www.gnu.org/copyleft/gpl.html "GNU General Public License 3"
This project is open-sourced software licensed under the [GPL license]
