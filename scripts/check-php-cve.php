<?php
// Aborta (código 1) si este PHP no tiene el parche de PHP2Shell.
//
// CVE-2026-109618 y CVE-2026-109619 (21-oct-2026): RCE sin autenticar en
// todo PHP que atiende peticiones web. Solo las corrigen estas versiones;
// 8.1 y anteriores no tienen versión oficial corregida. Lo ejecuta el
// instalador antes de tocar nada, para que un PHP vulnerable no llegue
// a servir Moodle sin que nadie se entere.

$fixed  = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
$branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$ok     = isset($fixed[$branch]) ? version_compare(PHP_VERSION, $fixed[$branch], '>=')
                                 : version_compare($branch, '8.5', '>');

fwrite(STDERR, 'PHP ' . PHP_VERSION . ($ok
    ? " parcheado frente a PHP2Shell.\n"
    : " VULNERABLE a PHP2Shell (CVE-2026-109618/109619). Usa una imagen con PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 o posterior.\n"));
exit($ok ? 0 : 1);
