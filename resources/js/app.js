import Alpine from 'alpinejs';
import communityPreview from './community-preview';
import { loadDictionary } from './i18n';
import quiz from './quiz';

Alpine.data('quiz', quiz);
Alpine.data('communityPreview', communityPreview);
window.Alpine = Alpine;
loadDictionary().then(() => Alpine.start());
