import {
  Injectable,
  ConflictException,
  InternalServerErrorException,
  NotFoundException,
  ForbiddenException,
  OnApplicationBootstrap,
  Logger,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import * as bcrypt from 'bcrypt';
import { User, UserRole } from './entities/user.entity';
import { Agence } from '../agences/entities/agence.entity';
import { ActionSpeciale } from '../permissions/entities/action-speciale.entity';
import { UserActionSpeciale } from './entities/user-action-speciale.entity';
import { WhatsappService } from '../notifications/whatsapp.service';
import { Role } from '../roles/entities/role.entity';

export interface CreateUserDto {
  username: string;
  password?: string;
  nom_complet: string;
  role: UserRole;
  code_acces?: number;
  agence_id?: number;
  phone?: string;
  email?: string;
}

export interface UpdateUserDto {
  nom_complet?: string;
  role?: UserRole;
  code_acces?: number;
  agence_id?: number | null;
  phone?: string | null;
  email?: string | null;
  actif?: boolean;
}

@Injectable()
export class UsersService implements OnApplicationBootstrap {
  private readonly logger = new Logger(UsersService.name);

  constructor(
    @InjectRepository(User)
    private usersRepository: Repository<User>,
    @InjectRepository(Agence)
    private agenceRepository: Repository<Agence>,
    @InjectRepository(ActionSpeciale)
    private actionSpecialeRepository: Repository<ActionSpeciale>,
    @InjectRepository(UserActionSpeciale)
    private userActionSpecialeRepository: Repository<UserActionSpeciale>,
    @InjectRepository(Role)
    private roleRepository: Repository<Role>,
    private whatsappService: WhatsappService,
  ) {}

  private async roleEntityForCode(
    code: UserRole,
  ): Promise<Role | null> {
    return this.roleRepository.findOne({ where: { code } as any });
  }

  async onApplicationBootstrap() {
    if (process.env.NODE_ENV === 'test') {
      return;
    }
    this.logger.log(
      'Bootstrap: aucun seed utilisateur de test automatique (comportement production).',
    );
  }

  // ─── CRUD PRINCIPAL ────────────────────────────────────────────────────

  /** Créer un utilisateur (superadmin ou DG) */
  async createUser(dto: CreateUserDto): Promise<User> {
    const existing = await this.usersRepository.findOne({
      where: { username: dto.username },
    });
    if (existing)
      throw new ConflictException("Ce nom d'utilisateur existe déjà");

    const temporaryPassword =
      dto.password?.trim() || this.generateTemporaryPassword();
    const hashedPassword = await bcrypt.hash(temporaryPassword, 10);
    let agence: Agence | null = null;
    if (dto.agence_id) {
      agence = await this.agenceRepository.findOne({
        where: { id: dto.agence_id },
      });
    }

    const roleEntity = await this.roleEntityForCode(dto.role);

    const user = this.usersRepository.create({
      username: dto.username,
      password: hashedPassword,
      password_plain: temporaryPassword, // mdp visible jusqu'au 1er changement
      must_change_password: true,
      agence_selected: !!agence,
      nom_complet: dto.nom_complet,
      role: dto.role,
      code_acces: dto.code_acces ?? (dto.role === UserRole.DIRECTEUR ? 2 : 1),
      agence,
      roleEntity: roleEntity ?? undefined,
      phone: dto.phone ?? null,
      email: dto.email?.trim() ? dto.email.trim() : null,
      actif: true,
    } as User);

    return this.usersRepository.save(user);
  }

  private generateTemporaryPassword(length: number = 10): string {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let pwd = '';
    for (let i = 0; i < length; i++) {
      pwd += alphabet.charAt(Math.floor(Math.random() * alphabet.length));
    }
    return pwd;
  }

  /** Liste tous les utilisateurs avec leur agence */
  async findAll(): Promise<User[]> {
    return this.usersRepository.find({
      relations: ['agence', 'roleEntity'],
      order: { created_at: 'DESC' },
    });
  }

  /** Détail d'un utilisateur */
  async findOne(id: number): Promise<User> {
    const user = await this.usersRepository.findOne({
      where: { id },
      relations: ['agence', 'roleEntity'],
    });
    if (!user) throw new NotFoundException('Utilisateur introuvable');
    return user;
  }

  /** Modifier un utilisateur (nom, rôle, agence, statut) */
  async updateUser(id: number, dto: UpdateUserDto): Promise<User> {
    const user = await this.findOne(id);

    if (dto.nom_complet !== undefined) user.nom_complet = dto.nom_complet;
    if (dto.role !== undefined) {
      user.role = dto.role;
      const re = await this.roleEntityForCode(dto.role);
      user.roleEntity = re;
    }
    if (dto.code_acces !== undefined) user.code_acces = dto.code_acces;
    if (dto.phone !== undefined) user.phone = dto.phone ?? null;
    if (dto.email !== undefined) user.email = dto.email ?? null;
    if (dto.actif !== undefined) user.actif = dto.actif;

    if (dto.agence_id !== undefined) {
      if (dto.agence_id === null) {
        user.agence = null;
      } else {
        const agence = await this.agenceRepository.findOne({
          where: { id: dto.agence_id },
        });
        if (!agence) throw new NotFoundException('Agence introuvable');
        user.agence = agence;
      }
    }

    return this.usersRepository.save(user);
  }

  /** Activer ou désactiver un utilisateur */
  async toggleActive(id: number): Promise<User> {
    const user = await this.findOne(id);
    user.actif = !user.actif;
    return this.usersRepository.save(user);
  }

  /** Supprimer (soft delete — désactive l'utilisateur) */
  async deleteUser(id: number): Promise<void> {
    const user = await this.findOne(id);
    user.actif = false;
    await this.usersRepository.save(user);
  }

  // ─── GESTION MOT DE PASSE ──────────────────────────────────────────────

  /**
   * Reset mdp par superadmin/DG : génère un nouveau mdp temporaire visible.
   * Re-force le changement à la prochaine connexion.
   */
  async resetPassword(id: number, newPassword: string): Promise<void> {
    const user = await this.usersRepository.findOne({ where: { id } });
    if (!user) throw new NotFoundException('Utilisateur introuvable');

    user.password = await bcrypt.hash(newPassword, 10);
    user.password_plain = newPassword; // visible en clair jusqu'au prochain changement
    user.must_change_password = true; // force re-changement
    await this.usersRepository.save(user);
  }

  /**
   * Changement de mdp par l'utilisateur lui-même (1ère connexion ou volontaire).
   * Efface password_plain et désactive la contrainte de changement.
   */
  async changePassword(
    id: number,
    oldPassword: string,
    newPassword: string,
  ): Promise<void> {
    const user = await this.usersRepository
      .createQueryBuilder('user')
      .addSelect('user.password')
      .where('user.id = :id', { id })
      .getOne();
    if (!user) throw new NotFoundException('Utilisateur introuvable');

    const isValid = await bcrypt.compare(oldPassword, user.password);
    if (!isValid) throw new ForbiddenException('Mot de passe actuel incorrect');

    user.password = await bcrypt.hash(newPassword, 10);
    user.password_plain = null; // effacer le mdp temporaire
    user.must_change_password = false; // ne plus forcer
    await this.usersRepository.save(user);
  }

  /**
   * Retourner le mdp temporaire en clair (superadmin/DG uniquement).
   * Si password_plain est null → l'utilisateur a déjà changé son mdp.
   */
  async getPasswordPlain(
    id: number,
  ): Promise<{ password_plain: string | null; changed: boolean }> {
    const user = await this.usersRepository.findOne({ where: { id } });
    if (!user) throw new NotFoundException('Utilisateur introuvable');

    return {
      password_plain: user.password_plain,
      changed: user.password_plain === null,
    };
  }

  /**
   * Envoyer le mot de passe temporaire via WhatsApp/SMS.
   * Nécessite un numéro de téléphone et un password_plain disponible.
   */
  async sendTemporaryPassword(
    id: number,
  ): Promise<{ sent: boolean; message: string }> {
    const user = await this.usersRepository.findOne({ where: { id } });
    if (!user) throw new NotFoundException('Utilisateur introuvable');

    if (!user.phone) {
      return {
        sent: false,
        message: 'Numéro de téléphone non renseigné pour cet utilisateur.',
      };
    }

    if (!user.password_plain) {
      return {
        sent: false,
        message:
          'Le mot de passe temporaire n’est plus disponible (déjà modifié par l’utilisateur).',
      };
    }

    const safePhone = user.phone.trim();
    const tempPassword = user.password_plain;
    const appUrl = process.env.APP_URL || 'https://labelleporte.cloud';
    const displayName = user.nom_complet || user.username;

    const text =
      `Bonjour ${displayName}, voici vos accès temporaires LBP.\n` +
      `Identifiant: ${user.username}\n` +
      `Mot de passe temporaire: ${tempPassword}\n` +
      `Connexion: ${appUrl}\n` +
      `Veuillez changer ce mot de passe dès votre première connexion.`;

    const sent = await this.whatsappService.sendMessage(safePhone, text);
    if (!sent) {
      return {
        sent: false,
        message: 'Échec de l’envoi du message. Veuillez réessayer.',
      };
    }

    return {
      sent: true,
      message: `Mot de passe temporaire envoyé à ${safePhone}.`,
    };
  }

  // ─── SÉLECTION D'AGENCE (1ère CONNEXION) ───────────────────────────────

  /**
   * L'utilisateur choisit son agence lors de la 1ère connexion.
   * Une fois choisie, elle est mémorisée et ne sera plus demandée.
   */
  async setAgence(userId: number, agenceId: number): Promise<User> {
    const user = await this.findOne(userId);
    const agence = await this.agenceRepository.findOne({
      where: { id: agenceId },
    });
    if (!agence) throw new NotFoundException('Agence introuvable');

    user.agence = agence;
    user.agence_selected = true;
    return this.usersRepository.save(user);
  }

  // ─── REQUÊTES INTERNES ─────────────────────────────────────────────────

  async findByUsername(username: string): Promise<User | null> {
    return this.usersRepository
      .createQueryBuilder('user')
      .addSelect('user.password')
      .leftJoinAndSelect('user.agence', 'agence')
      .leftJoinAndSelect('user.roleEntity', 'roleEntity')
      .where('user.username = :username', { username })
      .getOne();
  }

  async findById(id: number): Promise<User | null> {
    return this.usersRepository.findOne({
      where: { id },
      relations: ['agence', 'roleEntity'],
    });
  }

  // ─── STATS POUR DASHBOARD ──────────────────────────────────────────────

  async getUserStats(): Promise<{
    total: number;
    actifs: number;
    inactifs: number;
    parRole: Record<string, number>;
  }> {
    const users = await this.usersRepository.find();
    const parRole: Record<string, number> = {};
    let actifs = 0;

    for (const u of users) {
      if (u.actif) actifs++;
      parRole[u.role] = (parRole[u.role] || 0) + 1;
    }

    return {
      total: users.length,
      actifs,
      inactifs: users.length - actifs,
      parRole,
    };
  }

  // ─── SEED (utilisateurs de test) ───────────────────────────────────────

  async createDefaultAdmin() {
    const testUsers = [
      {
        username: 'admin',
        password: 'adminpassword',
        nom_complet: 'Administrateur Système',
        role: UserRole.DIRECTEUR,
        code_acces: 2,
        agence_code: 'DG',
      },
      {
        username: 'kadjo',
        password: 'adminpassword',
        nom_complet: 'Kadjo Serge',
        role: UserRole.DIRECTEUR,
        code_acces: 2,
        agence_code: 'FR-PARIS',
      },
      {
        username: 'adja',
        password: 'password123',
        nom_complet: 'Adja Nadege',
        role: UserRole.MANAGER,
        code_acces: 1,
        agence_code: 'CI-ABOBO',
      },
      {
        username: 'adepo',
        password: 'password123',
        nom_complet: 'Adepo Marie-Estelle',
        role: UserRole.MANAGER,
        code_acces: 1,
        agence_code: 'CI-AEROPORT',
      },
      {
        username: 'mon',
        password: 'password123',
        nom_complet: 'Mon Bertrand',
        role: UserRole.AGENT_EXPLOITATION,
        code_acces: 1,
        agence_code: 'CI-AEROPORT',
      },
      {
        username: 'abia',
        password: 'password123',
        nom_complet: 'Abia Valerie',
        role: UserRole.CAISSIER,
        code_acces: 1,
        agence_code: 'DG',
      },
      {
        username: 'ndri',
        password: 'password123',
        nom_complet: "N'Dri Marina",
        role: UserRole.AGENT_GROUPAGE,
        code_acces: 1,
        agence_code: 'DG',
      },
      {
        username: 'dialy',
        password: 'password123',
        nom_complet: 'Dialy Edwige',
        role: UserRole.AGENT_GROUPAGE,
        code_acces: 1,
        agence_code: 'DG',
      },
      {
        username: 'kabore',
        password: 'password123',
        nom_complet: 'Kabore Nelly',
        role: UserRole.AGENT_SUIVI,
        code_acces: 1,
        agence_code: 'DG',
      },
      {
        username: 'akoiblin',
        password: 'password123',
        nom_complet: 'AKOIBLIN ROXANNE',
        role: UserRole.SUPERVISEUR_REGIONAL,
        code_acces: 1,
        agence_code: 'CI-ADJAME',
        special_actions: [
          'pageAgence',
          'voirToutesAgences',
          'nePeutPasSupprimer',
          'uniquementGroupage',
          'nePeutPasModifier',
          'pageIndividuelle',
        ],
      },
      {
        username: 'ouedraogo',
        password: 'password123',
        nom_complet: 'Ouedraogo Mondesire',
        role: UserRole.CAISSIER_GROUPAGE,
        code_acces: 1,
        agence_code: 'SN-DAKAR',
      },
    ];

    for (const userData of testUsers) {
      await this.createOrResetTestUser(userData);
    }
  }

  async createOrResetTestUser(userData: {
    username: string;
    password: string;
    nom_complet: string;
    role: UserRole;
    code_acces: number;
    agence_code?: string;
    special_actions?: string[];
  }) {
    const existingUser = await this.findByUsername(userData.username);
    let agence: Agence | null = null;

    if (userData.agence_code) {
      agence = await this.agenceRepository.findOne({
        where: { code: userData.agence_code },
      });
    }

    if (!existingUser) {
      const hashedPassword = await bcrypt.hash(userData.password, 10);
      const newUser = this.usersRepository.create({
        username: userData.username,
        password: hashedPassword,
        password_plain: userData.password,
        must_change_password: false, // seed users → pas de contrainte
        agence_selected: agence ? true : false,
        nom_complet: userData.nom_complet,
        role: userData.role,
        code_acces: userData.code_acces,
        agence,
        actif: true,
      } as User);
      const saved = await this.usersRepository.save(newUser);
      console.log(
        `✅ User created: ${userData.username} (Agence: ${userData.agence_code || 'N/A'})`,
      );

      if (userData.special_actions) {
        await this.linkSpecialActions(saved, userData.special_actions);
      }
    } else {
      const isPasswordCorrect = await bcrypt.compare(
        userData.password,
        existingUser.password,
      );
      let needsUpdate = false;

      if (!isPasswordCorrect) {
        existingUser.password = await bcrypt.hash(userData.password, 10);
        existingUser.password_plain = userData.password;
        needsUpdate = true;
      }
      if (
        agence &&
        (!existingUser.agence || (existingUser.agence as any).id !== agence.id)
      ) {
        existingUser.agence = agence;
        existingUser.agence_selected = true;
        needsUpdate = true;
      }
      if (!existingUser.actif) {
        existingUser.actif = true;
        needsUpdate = true;
      }
      if (needsUpdate) {
        await this.usersRepository.save(existingUser);
        console.log(`✅ User ${userData.username} updated`);
      }
      if (userData.special_actions) {
        await this.linkSpecialActions(existingUser, userData.special_actions);
      }
    }
  }

  private async linkSpecialActions(user: User, actionCodes: string[]) {
    await this.userActionSpecialeRepository.delete({ user: { id: user.id } });
    for (const code of actionCodes) {
      const action = await this.actionSpecialeRepository.findOne({
        where: { code },
      });
      if (action) {
        const userAction = this.userActionSpecialeRepository.create({
          user,
          actionSpeciale: action,
        });
        await this.userActionSpecialeRepository.save(userAction);
      }
    }
  }

  // Méthode legacy conservée pour compatibilité
  async create(userData: Partial<User>): Promise<User> {
    try {
      const { password, ...rest } = userData;
      if (!password)
        throw new InternalServerErrorException('Password is required');
      const hashedPassword = await bcrypt.hash(password, 10);
      const user = this.usersRepository.create({
        ...rest,
        password: hashedPassword,
      } as User);
      return this.usersRepository.save(user);
    } catch (error: any) {
      if (error.code === '23505')
        throw new ConflictException('Username already exists');
      throw error;
    }
  }
}
