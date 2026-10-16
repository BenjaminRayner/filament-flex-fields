import { createRatingRuntime } from '../rating/runtime.js'

export default function ratingFieldFormComponent(configuration) {
    return createRatingRuntime(configuration)
}
