import { initMap } from './map';
import { initRipple } from './motion';
import { loadDataStatus } from './status';

document.addEventListener('DOMContentLoaded', () => {
    initRipple();
    if (document.getElementById('map')) {
        initMap();
        loadDataStatus();
    }
});
