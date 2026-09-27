import { initMap } from './map';
import { loadDataStatus } from './status';

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('map')) {
        initMap();
        loadDataStatus();
    }
});
