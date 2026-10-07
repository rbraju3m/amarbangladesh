import Alpine from 'alpinejs';
import quiz from './quiz';

Alpine.data('quiz', quiz);
window.Alpine = Alpine;
Alpine.start();
