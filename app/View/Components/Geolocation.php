<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class Geolocation
{
    /** @param array<string,mixed> $options */
    public static function fields(array $options = []): string
    {
        $latitudeName = (string) ($options['latitudeName'] ?? 'latitude');
        $longitudeName = (string) ($options['longitudeName'] ?? 'longitude');
        $addressName = (string) ($options['addressName'] ?? 'address');
        $latitude = (string) ($options['latitude'] ?? '');
        $longitude = (string) ($options['longitude'] ?? '');
        $address = (string) ($options['address'] ?? '');
        $compact = !empty($options['compact']);
        $class = Html::classes(['finea-geolocation', 'is-compact' => $compact, (string) ($options['class'] ?? '')]);
        $idPrefix = preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            (string) ($options['idPrefix'] ?? $latitudeName . '_' . $longitudeName)
        );
        $statusId = 'geolocation_status_' . $idPrefix;
        $resultsId = 'geolocation_results_' . $idPrefix;
        $mapId = 'geolocation_map_' . $idPrefix;

        return '<div class="' . View::e($class) . '" data-geolocation'
            . ' data-search-endpoint="https://photon.komoot.io/api/"'
            . ' data-reverse-endpoint="https://photon.komoot.io/reverse">'
            . Form::hidden($latitudeName, $latitude, [
                'id' => 'geolocation_latitude_' . $idPrefix,
                'data-geolocation-latitude' => true,
            ])
            . Form::hidden($longitudeName, $longitude, [
                'id' => 'geolocation_longitude_' . $idPrefix,
                'data-geolocation-longitude' => true,
            ])
            . Form::hidden($addressName, $address, [
                'id' => 'geolocation_address_' . $idPrefix,
                'data-geolocation-address' => true,
            ])
            . '<div class="finea-geolocation-search">'
            . '<label for="geolocation_search_' . View::e($idPrefix) . '">Rechercher une position</label>'
            . '<div class="finea-geolocation-searchbar">'
            . '<input class="finea-input" type="search" id="geolocation_search_' . View::e($idPrefix) . '"'
            . ' value="' . View::e($address) . '" placeholder="Quartier, rue, ville, agence..."'
            . ' autocomplete="off" aria-controls="' . View::e($resultsId) . '" data-geolocation-search>'
            . '<button class="finea-geolocation-button" type="button" data-geolocation-trigger'
            . ' aria-label="Utiliser ma position actuelle" title="Utiliser ma position actuelle"'
            . ' aria-describedby="' . View::e($statusId) . '">'
            . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="3"></circle>'
            . '<path d="M12 2v3M12 19v3M2 12h3M19 12h3"></path>'
            . '<circle cx="12" cy="12" r="7"></circle>'
            . '</svg><span class="finea-sr-only">Utiliser ma position actuelle</span></button>'
            . '</div>'
            . '<div class="finea-geolocation-results" id="' . View::e($resultsId) . '"'
            . ' role="listbox" data-geolocation-results hidden></div>'
            . '</div>'
            . '<div class="finea-geolocation-map" id="' . View::e($mapId) . '" data-geolocation-map'
            . ' aria-label="Carte de sélection de la position"></div>'
            . '<small id="' . View::e($statusId) . '" class="finea-geolocation-status" data-geolocation-status>'
            . ($address !== ''
                ? 'Position enregistrée : ' . View::e($address)
                : 'Recherchez un lieu, cliquez sur la carte ou utilisez votre position actuelle.')
            . '</small></div>';
    }
}
