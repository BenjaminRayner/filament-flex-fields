{{-- One configurable card; native map state, forms and history remain outside its datasets. --}}
<section class="fff-location__experience-card" x-show="mapReady && experienceCard" x-cloak
    x-bind:style="{'--fff-map-card-width': `${experienceCard?.width ?? 250}px`}" x-bind:data-position="experienceCard?.position" x-bind:aria-label="experienceCard?.title">
    <div class="fff-location__experience-heading">
        <span class="fff-location__experience-avatar" x-show="experienceCard?.avatarLabel" role="img" x-bind:aria-label="experienceCard?.avatarLabel"><img class="fff-location__experience-avatar-image" x-show="experienceCard?.avatarUrl" x-bind:src="experienceCard?.avatarUrl || null" x-bind:alt="experienceCard?.avatarLabel || ''" /></span>
        <div class="fff-location__experience-heading-text">
        <h3 class="fff-location__experience-title" x-text="experienceCard?.title"></h3>
        <p class="fff-location__experience-description" x-show="experienceCard?.description" x-text="experienceCard?.description"></p>
        </div>
    </div>
    <div class="fff-location__experience-statistics" x-show="experienceCard?.statistics?.length">
        <template x-for="statistic in experienceCard?.statistics ?? []" x-bind:key="statistic.label">
            <div class="fff-location__experience-statistic"><strong x-text="statistic.value"></strong><span x-text="statistic.label"></span></div>
        </template>
    </div>
    <div class="fff-location__experience-progress" x-show="experienceCard?.progress !== null && experienceCard?.progress !== undefined">
        <div class="fff-location__experience-progress-label"><span x-text="experienceCard?.progressLabel"></span><strong x-text="experienceCard?.progressValue"></strong></div>
        <div class="fff-location__experience-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
            x-bind:aria-valuenow="Math.round((experienceCard?.progress ?? 0) * 100)" x-bind:aria-label="experienceCard?.progressLabel">
            <span class="fff-location__experience-progress-fill" x-bind:style="{width: `${Math.round((experienceCard?.progress ?? 0) * 100)}%`}" aria-hidden="true"></span>
        </div>
    </div>
    <div class="fff-location__experience-scale" x-show="experienceCard?.gradient?.length">
        <div class="fff-location__experience-gradient" x-bind:style="experienceGradient()" aria-hidden="true"></div>
        <div class="fff-location__experience-scale-labels"><span x-text="experienceCard?.lowLabel"></span><span x-text="experienceCard?.highLabel"></span></div>
    </div>
    <div class="fff-location__experience-list" x-show="experienceCard?.layer">
        <template x-for="feature in experienceListings()" x-bind:key="feature.id">
            <button type="button" class="fff-location__experience-listing"
                x-bind:class="{'is-selected': experienceSelection === `${experienceCard.layer}:${feature.id}`}"
                x-bind:aria-pressed="experienceSelection === `${experienceCard.layer}:${feature.id}`"
                x-on:pointerenter="hoverExperienceFeature(experienceCard.layer, feature.id)" x-on:pointerleave="hoverExperienceFeature(experienceCard.layer, null)"
                x-on:click="selectExperienceFeature(experienceCard.layer, feature.id)">
                <span class="fff-location__experience-listing-text"><span x-text="feature.properties.label || feature.id"></span><small x-text="feature.properties.listingDetail || feature.properties.detail || feature.properties.description || ''"></small></span>
                <strong class="fff-location__experience-listing-value" x-text="feature.properties.value || feature.properties.metric || ''"></strong>
            </button>
        </template>
    </div>
</section>
