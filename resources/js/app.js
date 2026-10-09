import Alpine from 'alpinejs';
import communityPreview from './community-preview';
import { loadDictionary } from './i18n';
import modal from './modal';
import quiz from './quiz';
import siteNav from './site-nav';

Alpine.plugin(modal);
Alpine.data('quiz', quiz);
Alpine.data('siteNav', siteNav);
Alpine.data('communityPreview', communityPreview);
window.Alpine = Alpine;
loadDictionary().then(() => Alpine.start());
