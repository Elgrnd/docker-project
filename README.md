# 🖥️ DevBox — Plateforme de VMs pour étudiants

Application web permettant aux étudiants et aux enseignants de créer
et gérer des machines virtuelles via **Proxmox**, offrant un environnement
de développement uniforme, isolé et sécurisé.

## 🎯 Objectif

Garantir à chaque étudiant un environnement de développement identique
et isolé, évitant les problèmes de compatibilité entre machines personnelles
et assurant la sécurité grâce à la conteneurisation.

## ✨ Fonctionnalités

- 🎓 **Espace étudiant** — création et gestion de sa propre VM
- 👨‍🏫 **Espace enseignant** — supervision et gestion des VMs étudiants
- 🔒 **Isolation des environnements** — chaque VM est indépendante
- ⚙️ **Provisioning automatique** via l'API Proxmox
- 🌐 **Interface web intuitive** accessible depuis un navigateur

## 🛠️ Technologies utilisées

- **Docker** & **Docker Compose** — conteneurisation de l'application
- **Proxmox VE** — hyperviseur pour la gestion des VMs
- **PHP / Symfony** — backend & API REST
- **Vue.js** — interface utilisateur dynamique
- **MySQL / PostgreSQL** — base de données
- **Nginx** — serveur web

## 🚀 Installation & lancement

```bash
# Cloner le dépôt
git clone https://github.com/Elgrnd/docker-project.git
cd docker-project

# Configurer les variables d'environnement
cp .env.example .env
# Renseigner les credentials Proxmox et BDD dans .env
```

## ⚙️ Configuration

Dans le fichier `.env`, renseigner :

```env
APP_SECRET=your_app_secret_here

DATABASE_URL=your_database_url_here

PROXMOX_API_URL=your_proxmox_api_url
PROXMOX_TOKEN_ID=your_token_id
PROXMOX_TOKEN_SECRET=your_token_secret
PROXMOX_HOTE=your_proxmox_host_ip

GITLAB_TOKEN_KEY=your_gitlab_token
```

## 👥 Rôles utilisateurs

| Rôle       | Permissions                              |
|------------|------------------------------------------|
| Étudiant   | Créer, démarrer, arrêter sa VM           |
| Enseignant | Gérer toutes les VMs, créer des gabarits |
| Admin      | Administration complète de la plateforme |

## 👤 Auteurs

- **Elgrnd** — [GitHub](https://github.com/Elgrnd)
- **YayaBj** — [GitHub](https://github.com/YayaBj)
- **NapstaCap** — [GitHub](https://github.com/NapstaCap)
- **LaurentGabin** — [GitHub](https://github.com/LaurentGabin)
- **Alex-ilix** — [GitHub](https://github.com/Alex-ilix)
- **Nikholah** — [GitHub](https://github.com/Nikholah)
