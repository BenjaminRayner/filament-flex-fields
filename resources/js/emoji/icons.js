import smile from '../../../vendor/janczakb/blade-hugeicons/resources/svg/smile.svg'
import people from '../../../vendor/janczakb/blade-hugeicons/resources/svg/user-group.svg'
import earth from '../../../vendor/janczakb/blade-hugeicons/resources/svg/earth.svg'
import coffee from '../../../vendor/janczakb/blade-hugeicons/resources/svg/coffee-01.svg'
import car from '../../../vendor/janczakb/blade-hugeicons/resources/svg/car-01.svg'
import target from '../../../vendor/janczakb/blade-hugeicons/resources/svg/target-01.svg'
import bulb from '../../../vendor/janczakb/blade-hugeicons/resources/svg/bulb.svg'
import hashtag from '../../../vendor/janczakb/blade-hugeicons/resources/svg/hashtag.svg'
import flag from '../../../vendor/janczakb/blade-hugeicons/resources/svg/flag-01.svg'
import sparkles from '../../../vendor/janczakb/blade-hugeicons/resources/svg/sparkles.svg'
import search from '../../../vendor/janczakb/blade-hugeicons/resources/svg/search-01.svg'
const categories = Object.freeze({ '-1': sparkles, 0: smile, 1: people, 3: earth, 4: coffee, 5: car, 6: target, 7: bulb, 8: hashtag, 9: flag })
export const getCategoryIconGroupIds = () => ['-1', '0', '1', '3', '4', '5', '6', '7', '8', '9']
export const getCategoryIconSvg = id => categories[String(id)] ?? null
export const prepareIconSvg = source => source.replace(/<svg\b/, '<svg class="fff-ui-icon" aria-hidden="true" focusable="false"')
    .replace(/\s(?:width|height)="[0-9]+"/g, '')
export function renderPickerIcons(root) {
    for (const button of root.querySelectorAll('.nav-button[data-group-id]')) {
        const host = button.querySelector('.nav-emoji'); const icon = getCategoryIconSvg(button.dataset.groupId)
        if (host && icon && !host.querySelector('.fff-ui-icon')) host.innerHTML = prepareIconSvg(icon)
    }
    const searchHost = root.querySelector('.search-wrapper')
    if (searchHost && !searchHost.querySelector('.fff-search-icon')) {
        const icon = document.createElement('span')
        icon.className = 'fff-search-icon'; icon.setAttribute('aria-hidden', 'true'); icon.innerHTML = prepareIconSvg(search)
        searchHost.prepend(icon)
    }
}
