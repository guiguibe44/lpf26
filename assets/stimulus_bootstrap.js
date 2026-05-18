import { startStimulusApp } from '@symfony/stimulus-bundle';

/**
 * Contrôleurs enregistrés via assets/controllers/*.js
 * (commentaire stimulusFetch: 'eager' ou lazy selon le fichier).
 */
const app = startStimulusApp();

export { app };
