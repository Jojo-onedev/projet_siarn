-- 0002_utilisateurs_auth.sql
-- SIARN — Comptes, MFA, sessions JWT, journal des connexions
-- PRD refs : §7.1 (gestion des utilisateurs et des accès), §13.1 (identités et accès)

CREATE TABLE utilisateurs (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nom                 VARCHAR(150) NOT NULL,
    prenom              VARCHAR(150) NOT NULL,
    email               CITEXT NOT NULL UNIQUE,
    mot_de_passe_hash   VARCHAR(255) NOT NULL, -- Bcrypt/Argon2 (§13.1), jamais géré côté DB
    role                role_utilisateur NOT NULL,
    statut_mfa          BOOLEAN NOT NULL DEFAULT false,
    secret_mfa          VARCHAR(255),          -- chiffré applicativement avant stockage
    tentatives_echec    SMALLINT NOT NULL DEFAULT 0,
    verrouille_jusqu_a  TIMESTAMPTZ,           -- verrouillage progressif anti brute-force (§13.1)
    dernier_login_a     TIMESTAMPTZ,
    actif               BOOLEAN NOT NULL DEFAULT true,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_utilisateurs_updated_at
    BEFORE UPDATE ON utilisateurs
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE INDEX idx_utilisateurs_role ON utilisateurs(role);

-- Sessions JWT actives : un JWT signé ne peut pas être révoqué à lui seul.
-- Cette table permet la révocation immédiate exigée en §13.1 (déconnexion,
-- changement de mot de passe, compromission suspectée) en faisant vérifier
-- au backend, à chaque requête, que le jti du token est bien présent et non révoqué.
-- Écart signalé : non explicitement décrit dans le modèle de données §10,
-- mais nécessaire pour tenir l'exigence de révocation de §13.1 avec des JWT.
CREATE TABLE sessions_jwt (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    utilisateur_id  UUID NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    jti             VARCHAR(255) NOT NULL UNIQUE,
    expire_a        TIMESTAMPTZ NOT NULL,
    revoque         BOOLEAN NOT NULL DEFAULT false,
    ip_creation     INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_sessions_jwt_utilisateur ON sessions_jwt(utilisateur_id);
CREATE INDEX idx_sessions_jwt_expire ON sessions_jwt(expire_a);

-- Journal des tentatives de connexion (succès ET échecs, y compris email inconnu).
-- Distinct du journal d'audit général (0007) qui trace les actions métier :
-- ici on veut pouvoir tracer une tentative même quand utilisateur_id est inconnu.
CREATE TABLE journal_connexions (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    utilisateur_id      UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    email_tentative     CITEXT NOT NULL,
    succes              BOOLEAN NOT NULL,
    motif_echec         VARCHAR(100), -- ex: 'mdp_invalide', 'mfa_invalide', 'compte_verrouille'
    ip                  INET,
    user_agent          TEXT,
    date_heure          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_journal_connexions_utilisateur ON journal_connexions(utilisateur_id);
CREATE INDEX idx_journal_connexions_date ON journal_connexions(date_heure);
