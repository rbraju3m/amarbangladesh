import Alpine from 'alpinejs';
import communityPreview from './community-preview';
import quiz from './quiz';

Alpine.data('quiz', quiz);
Alpine.data('communityPreview', communityPreview);
window.Alpine = Alpine;
Alpine.start();
