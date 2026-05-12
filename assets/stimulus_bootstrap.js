import { startStimulusApp } from '@symfony/stimulus-bundle';
import DashboardButeurController from './controllers/dashboard_buteur_controller.js';

const app = startStimulusApp();
app.register('dashboard-buteur', DashboardButeurController);
