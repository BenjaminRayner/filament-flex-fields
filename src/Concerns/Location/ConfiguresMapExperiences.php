<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapCard;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapLayer;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapTrackStop;
use Closure;
use InvalidArgumentException;

/** @mixin MapPickerField */
trait ConfiguresMapExperiences
{
    /** @var array{zoom: float|null, pitch: float|null, rotate: bool, pauseOnInteraction: bool}|null */
    protected ?array $experienceFollowCamera = null;

    public function followMapTrack(bool $enabled = true, ?float $zoom = null, ?float $pitch = null, bool $rotate = false, bool $pauseOnInteraction = true): static
    {
        if (($zoom !== null && (! is_finite($zoom) || $zoom < 0 || $zoom > 22)) || ($pitch !== null && (! is_finite($pitch) || $pitch < 0 || $pitch > 60))) {
            throw new InvalidArgumentException('Invalid tracking camera zoom or pitch.');
        }
        $this->experienceFollowCamera = $enabled ? compact('zoom', 'pitch', 'rotate', 'pauseOnInteraction') : null;

        return $this;
    }

    protected ?string $experienceRemainingRouteLayer = null;

    protected bool $experienceHidePassedWaypoints = false;

    /** Hide origin markers and completed stops using rendered route progress. */
    public function hidePassedMapWaypoints(bool $enabled = true): static
    {
        $this->experienceHidePassedWaypoints = $enabled;

        return $this;
    }

    /** Hide travelled geometry using the rendered marker's route progress. */
    public function remainingMapRoute(?string $layer): static
    {
        $this->experienceRemainingRouteLayer = $layer;

        return $this;
    }

    /** @var list<MapTrackStop> */
    protected array $experienceStops = [];

    /** @param list<MapTrackStop> $stops */
    public function mapTrackStops(array $stops): static
    {
        if (! array_is_list($stops) || count($stops) > 32) {
            throw new InvalidArgumentException('A track supports up to 32 typed stops.');
        }
        foreach ($stops as $stop) {
            if (! $stop instanceof MapTrackStop) {
                throw new InvalidArgumentException('Track stops require MapTrackStop instances.');
            }
        }
        $this->experienceStops = $stops;

        return $this;
    }

    /** @var list<MapLayer>|Closure */
    protected array|Closure $experienceLayers = [];

    protected MapCard|Closure|null $experienceCard = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $experienceFeed = null;

    /** @var array<string, mixed>|null */
    protected ?array $experiencePlayback = null;

    /** @var array{layer: string, feature: string}|null */
    protected ?array $experienceInitialSelection = null;

    /** @var array<string, mixed>|null */
    protected ?array $experienceLiveTrack = null;

    /**
     * Timestamped route progress is supplied by the application, not inferred GPS.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function liveMapTrack(string $layer, string $feature, array $coordinates, int $recoveryDuration = 2500, int $staleAfter = 15000): static
    {
        if (! array_is_list($coordinates) || count($coordinates) < 2 || count($coordinates) > 1000 || $recoveryDuration < 100 || $recoveryDuration > 10000 || $staleAfter < 1000 || $staleAfter > 300000) {
            throw new InvalidArgumentException('Invalid live track or recovery policy.');
        }
        $coordinates = array_map(Coordinates::pair(...), $coordinates);
        if (count(array_unique(array_map(static fn (array $point): string => json_encode($point, JSON_THROW_ON_ERROR), $coordinates))) < 2) {
            throw new InvalidArgumentException('A live track must have a nonzero route.');
        }
        $this->experienceLiveTrack = compact('layer', 'feature', 'coordinates', 'recoveryDuration', 'staleAfter');

        return $this;
    }

    public function mapInitialSelection(string $layer, string $feature): static
    {
        $this->experienceInitialSelection = ['layer' => $layer, 'feature' => $feature];

        return $this;
    }

    /**
     * Independent read-only datasets never become submitted feature state.
     *
     * @param  list<MapLayer>|Closure  $layers
     */
    public function mapLayers(array|Closure $layers): static
    {
        $this->experienceLayers = $layers;

        return $this;
    }

    public function mapCard(MapCard|Closure|null $card): static
    {
        $this->experienceCard = $card;

        return $this;
    }

    /**
     * Recorded WGS84 positions are explicitly distinct from live GPS.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function recordedMapTrack(string $layer, string $feature, array $coordinates, int $duration = 30000, bool $loop = false, bool $autoPlay = false, bool $controls = true, ?string $completedRouteLayer = null, ?int $arrivalMinutes = null, string $distanceMetric = 'ground'): static
    {
        if (! array_is_list($coordinates) || count($coordinates) < 2 || count($coordinates) > 1000 || $duration < 1000 || $duration > 3600000) {
            throw new InvalidArgumentException('Invalid recorded track.');
        }
        if (! in_array($distanceMetric, ['ground', 'coordinate'], true) || ($arrivalMinutes !== null && ($arrivalMinutes < 1 || $arrivalMinutes > 1440))) {
            throw new InvalidArgumentException('Invalid playback policy.');
        }
        $coordinates = array_map(Coordinates::pair(...), $coordinates);
        if (count(array_unique(array_map(static fn (array $point): string => json_encode($point, JSON_THROW_ON_ERROR), $coordinates))) < 2) {
            throw new InvalidArgumentException('A recorded track must have a nonzero route.');
        }
        $this->experiencePlayback = ['completedRouteLayer' => $completedRouteLayer, 'arrivalMinutes' => $arrivalMinutes, 'distanceMetric' => $distanceMetric, 'layer' => $layer, 'feature' => $feature, 'coordinates' => $coordinates, 'duration' => $duration, 'loop' => $loop, 'autoPlay' => $autoPlay, 'controls' => $controls];

        return $this;
    }

    /** Polling is opt-in, same-origin and paused when its owning panel is inactive. */
    public function liveMapEndpoint(string $endpoint, int $interval = 3000): static
    {
        if (! preg_match('~^/(?!/)[^\s]+$~D', $endpoint) || str_contains($endpoint, '\\') || str_contains($endpoint, '#') || strlen($endpoint) > 2048 || $interval < 1000 || $interval > 300000) {
            throw new InvalidArgumentException('Live map feeds require a same-origin path and interval between 1 and 300 seconds.');
        }
        $this->experienceFeed = ['endpoint' => $endpoint, 'interval' => $interval];

        return $this;
    }

    /** @return array{layers: list<array<string, mixed>>, card: array<string, mixed>|null, feed: array<string, mixed>|null, playback: array<string, mixed>|null, liveTrack: array<string, mixed>|null, remainingRouteLayer: string|null, hidePassedWaypoints: bool, followCamera: array{zoom: float|null, pitch: float|null, rotate: bool, pauseOnInteraction: bool}|null, stops: list<array{id: string, progress: float, label: string, wait: int}>, initialSelection: array{layer: string, feature: string}|null} */
    public function getMapExperienceConfiguration(): array
    {
        $layers = $this->evaluate($this->experienceLayers);
        if (! is_array($layers) || ! array_is_list($layers) || count($layers) > 16) {
            throw new InvalidArgumentException('mapLayers requires up to sixteen typed layers.');
        }
        $result = [];
        $identities = [];
        $bytes = 0;
        foreach ($layers as $layer) {
            if (! $layer instanceof MapLayer) {
                throw new InvalidArgumentException('mapLayers requires MapLayer instances.');
            }
            $configuration = $layer->toArray();
            $vehicleImage = match ($configuration['markerVariant'] ?? null) {
                'car' => 'delivery-car.png',
                'scooter' => 'delivery-scooter.png',
                default => null,
            };
            if ($vehicleImage !== null) {
                $configuration['markerImage'] ??= PublishedResourceUrl::for('map-picker', 'track-markers', $vehicleImage);
            }
            if (isset($identities[$configuration['id']])) {
                throw new InvalidArgumentException('Duplicate map layer identity.');
            }
            $identities[$configuration['id']] = true;
            $bytes += strlen(json_encode($configuration, JSON_THROW_ON_ERROR));
            if ($bytes > 4194304) {
                throw new InvalidArgumentException('Combined map layers exceed 4 MiB.');
            }
            $result[] = $configuration;
        }
        $card = $this->evaluate($this->experienceCard);
        if ($card !== null && ! $card instanceof MapCard) {
            throw new InvalidArgumentException('mapCard requires a MapCard.');
        }
        $card = $card?->toArray();
        if ($card !== null && $card['layer'] !== null && ! isset($identities[$card['layer']])) {
            throw new InvalidArgumentException('Listing cards must reference a configured layer.');
        }
        if ($this->experienceInitialSelection !== null) {
            $selection = $this->experienceInitialSelection;
            $matches = array_filter($result, fn (array $layer): bool => $layer['id'] === $selection['layer'] && count(array_filter($layer['data']['features'], fn (array $feature): bool => $feature['id'] === $selection['feature'])) === 1);
            if ($matches === []) {
                throw new InvalidArgumentException('Initial selection requires an existing layer feature.');
            }
        }
        if ($this->experiencePlayback !== null) {
            $playback = $this->experiencePlayback;
            $target = array_filter($result, fn (array $layer): bool => $layer['id'] === $playback['layer'] && $layer['kind'] === 'tracked'
                && count(array_filter($layer['data']['features'], fn (array $feature): bool => $feature['id'] === $playback['feature'])) === 1);
            if ($playback['completedRouteLayer'] !== null) {
                $route = array_filter($result, fn (array $layer): bool => $layer['id'] === $playback['completedRouteLayer'] && $layer['kind'] === 'geometry' && ! $layer['cluster'] && (count($layer['data']['features']) === 0 || (count($layer['data']['features']) === 1 && $layer['data']['features'][0]['geometry']['type'] === 'LineString')));
                if ($route === []) {
                    throw new InvalidArgumentException('Completed route requires a dedicated empty or single-line geometry layer.');
                }
            }
            if ($target === [] || $this->experienceFeed !== null || $this->experienceLiveTrack !== null) {
                throw new InvalidArgumentException('Playback requires a configured tracked marker and cannot share a map with a live feed.');
            }
        }

        if ($this->experienceLiveTrack !== null) {
            $track = $this->experienceLiveTrack;
            $target = array_filter($result, fn (array $layer): bool => $layer['id'] === $track['layer'] && $layer['kind'] === 'tracked'
                && count(array_filter($layer['data']['features'], fn (array $feature): bool => $feature['id'] === $track['feature'])) === 1);
            if ($target === []) {
                throw new InvalidArgumentException('Live tracking requires an existing tracked marker.');
            }
        }

        if ($this->experienceRemainingRouteLayer !== null) {
            $track = $this->experiencePlayback ?? $this->experienceLiveTrack;
            $routes = array_filter($result, fn (array $layer): bool => $layer['id'] === $this->experienceRemainingRouteLayer && ($layer['navigationRoute'] ?? false) && count($layer['data']['features']) === 1 && $layer['data']['features'][0]['geometry']['coordinates'] === ($track['coordinates'] ?? null));
            if ($track === null || $routes === [] || ($this->experiencePlayback['completedRouteLayer'] ?? null) === $this->experienceRemainingRouteLayer) {
                throw new InvalidArgumentException('Remaining route requires a dedicated navigation layer with the exact tracked geometry.');
            }
        }

        $stops = array_map(static fn (MapTrackStop $stop): array => $stop->toArray(), $this->experienceStops);
        if ($this->experienceHidePassedWaypoints && $this->experiencePlayback === null && $this->experienceLiveTrack === null) {
            throw new InvalidArgumentException('Passed waypoint visibility requires recorded or live route tracking.');
        }
        if ($this->experienceFollowCamera !== null && $this->experiencePlayback === null && $this->experienceLiveTrack === null) {
            throw new InvalidArgumentException('Tracking camera requires recorded or live route tracking.');
        }
        if ($stops !== [] && ($this->experienceLiveTrack === null && $this->experiencePlayback === null || isset($identities['fff-track-stops']))) {
            throw new InvalidArgumentException('Stops require a configured track and reserve the fff-track-stops layer.');
        }
        $stopIds = array_column($stops, 'id');
        $progresses = array_column($stops, 'progress');
        if (count(array_unique($stopIds)) !== count($stops) || count(array_unique($progresses)) !== count($stops)) {
            throw new InvalidArgumentException('Stop IDs and route positions must be unique.');
        }
        usort($stops, static fn (array $a, array $b): int => $a['progress'] <=> $b['progress']);

        return ['layers' => $result, 'card' => $card, 'feed' => $this->evaluate($this->experienceFeed), 'playback' => $this->experiencePlayback, 'liveTrack' => $this->experienceLiveTrack, 'followCamera' => $this->experienceFollowCamera, 'remainingRouteLayer' => $this->experienceRemainingRouteLayer, 'hidePassedWaypoints' => $this->experienceHidePassedWaypoints, 'stops' => $stops, 'initialSelection' => $this->experienceInitialSelection];
    }
}
