<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

// TODO musimy zapewnic, ze utworzony 
// obiekt kontrollera ma tylko jedna instancję - SINGLETON

// TODO 2 /dashboard -- wszystkei dnae
// /dashboard/12234 -- wyciagnie nam jakis elemtn o wskaznaym ID 12234
// REGEX

class Routing {

    private static $instances = [];
    public static $routes = [
        // ^login$ oznacza: zacznij od login i na tym skończ
        "^login$" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        // ^dashboard$ dla listy wszystkich danych
        "^dashboard$" => [
            "controller" => "DashboardController",
            "action" => "index"
        ],
        // ^dashboard/([0-9]+)$ wyłapie dashboard/ oraz cyfry po ukośniku
        "^dashboard/([0-9]+)$" => [
            "controller" => "DashboardController",
            "action" => "index" // inna akcja dla konkretnego elementu
        ],
        "^register$" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],

        "^$" => [ // Strona główna
            "controller" => "SecurityController",
            "action" => "login"
        ]
        
    ];

    public static function run(string $path) {

        foreach (self::$routes as $pattern => $route) {
            // Budujemy pełny regex z ogranicznikami, np. #^dashboard$#
            $regex = "#" . $pattern . "#";

            if (preg_match($regex, $path, $matches)) {
                $controllerName = $route["controller"];
                $action = $route["action"];

                // SINGLETON: Pobieramy lub tworzymy instancję
                if (!isset(self::$instances[$controllerName])) {
                    self::$instances[$controllerName] = new $controllerName();
                }
                $controllerObj = self::$instances[$controllerName];

                // Wyciągamy ID: $matches[0] to cały dopasowany ciąg, 
                // $matches[1] to pierwsza grupa w nawiasach (nasze ID)
                $id = $matches[1] ?? null;

                // Wywołujemy akcję z przekazanym ID
                $controllerObj->$action($id);
                return; // Kończymy działanie po znalezieniu dopasowania
            }
        }

        // Jeśli pętla się skończy i nic nie pasuje:
        include 'public/views/404.html';
    }
}