import { runtime } from '../runtime/resources.js'
import { createExperienceRuntime,firstCoordinate } from './runtime.js'

export function createMapExperiences(){return {
    experienceCard:null,experienceRevision:0,experienceSelection:null,mapSourceError:null,mapFeedStatus:'idle',trackPlaying:false,trackFollowing:false,
    initializeMapExperiences(){this.experienceCard=this.mapConfiguration.experience?.card??null},
    installMapExperiences(){const r=runtime(this);r.experience??=createExperienceRuntime(this,r);r.experience.install()},
    experienceListings(){void this.experienceRevision;return runtime(this).experience?.listings(this.experienceCard?.layer)??[]},
    selectExperienceFeature(layer,id){
        const r=runtime(this),feature=r.experience?.feature(layer,id)
        if(!feature||this.__fffPickerDisposed)return
        this.experienceSelection=`${layer}:${id}`;r.experience.selected(layer,id)
        const coordinate=firstCoordinate(feature)
        this.showFeaturePopup(feature,coordinate)

    },
    hoverExperienceFeature(layer,id){runtime(this).experience?.highlight(layer,id)},
    experienceGradient(){return this.experienceCard?.gradient?.length?{backgroundImage:`linear-gradient(to right, ${this.experienceCard.gradient.join(',')})`}:{}},
    toggleTrackPlayback(){runtime(this).experience?.playback()},
    resumeTrackFollowing(){runtime(this).experience?.recenter()},
    retryLiveFeed(){runtime(this).experience?.retry()},
}}
