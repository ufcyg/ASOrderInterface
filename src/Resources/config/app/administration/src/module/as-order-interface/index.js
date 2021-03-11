import './page/as-order-interface-overview';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('as-order-interface', {
    type: 'plugin',
    name: 'orderInterface',
    title: 'as-order-interface.general.mainMenuItemGeneral',
    description: 'as-order-interface.general.descriptionTextModule',
    color: '#0400ff',
    icon: 'default-communication-envelope',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        overview: {
            component: 'as-order-interface-overview',
            path: 'overview'
        },
    },

    navigation: [{
        label: 'as-order-interface.general.mainMenuItemGeneral',
        color: '#0400ff',
        path: 'as.order.interface.overview',
        icon: 'default-arrow-switch',
        position: 12
    }],
});