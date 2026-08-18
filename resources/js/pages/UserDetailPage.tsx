import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, Descriptions, Button, Typography, Spin, Alert, Tag, Select,
  Space, Popconfirm, message, Divider,
} from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import {
  useUser, useAssignRole, useSyncOrganizations, useUpdateAccountStatus,
  useResendPasswordSetupLink, ASSIGNABLE_ROLES,
} from '../api/users';
import { useOrganizationsList } from '../api/organizations';
import { usePermission } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

const ROLE_COLORS: Record<string, string> = {
  'Super Administrator': 'red',
  'Exploitant': 'volcano',
  'Opérateur': 'blue',
  'Technicien': 'geekblue',
  'Service Client': 'purple',
  'Finance': 'gold',
  'Client': 'green',
};

export default function UserDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const userId = Number(id);

  const { data: user, isLoading } = useUser(userId);
  const { data: organizations } = useOrganizationsList();

  const canUpdate = usePermission('users.update');
  const canDisable = usePermission('users.disable');

  const assignRoleMutation = useAssignRole();
  const syncOrgsMutation = useSyncOrganizations();
  const statusMutation = useUpdateAccountStatus();
  const resendMutation = useResendPasswordSetupLink();

  const [selectedRole, setSelectedRole] = useState<string | undefined>(undefined);
  const [selectedOrgIds, setSelectedOrgIds] = useState<number[]>([]);

  useEffect(() => {
    if (user) {
      setSelectedRole(user.roles[0]);
      setSelectedOrgIds(user.organizations.map((o) => o.id));
    }
  }, [user]);

  if (isLoading) return <AppShell><div className="station-page-state"><Spin /></div></AppShell>;
  if (!user) return <AppShell><Alert className="station-page-error" type="error" message="Utilisateur introuvable" /></AppShell>;

  const currentRole = user.roles[0];
  const isPrivileged = ['Super Administrator', 'Exploitant', 'Finance'].includes(currentRole);

  const handleAssignRole = async () => {
    if (!selectedRole) return;
    try {
      await assignRoleMutation.mutateAsync({ id: userId, role: selectedRole });
      message.success('Rôle mis à jour');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleSyncOrganizations = async () => {
    try {
      await syncOrgsMutation.mutateAsync({ id: userId, organization_ids: selectedOrgIds });
      message.success('Organisations mises à jour');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const toggleAccountStatus = async () => {
    const next = user.account_status === 'active' ? 'disabled' : 'active';
    try {
      await statusMutation.mutateAsync({ id: userId, account_status: next });
      message.success(next === 'active' ? 'Compte activé' : 'Compte désactivé');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleResend = async () => {
    try {
      await resendMutation.mutateAsync(userId);
      message.success('Lien de configuration renvoyé');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  return (
    <AppShell>
      <main className="station-page station-page--detail">
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/users')}>
          Retour à la liste
        </Button>

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <Title className="station-page-title" level={3}>{user.name}</Title>
          <Tag color={user.account_status === 'active' ? 'green' : 'red'} style={{ fontSize: 13, padding: '4px 10px' }}>
            {user.account_status === 'active' ? 'Actif' : 'Désactivé'}
          </Tag>
        </div>

        <Card className="station-info-card">
          <Descriptions column={{ xs: 1, sm: 2 }} bordered size="small">
            <Descriptions.Item label="Nom">{user.name}</Descriptions.Item>
            <Descriptions.Item label="Email">{user.email}</Descriptions.Item>
            <Descriptions.Item label="Rôle(s)">
              {user.roles.length === 0
                ? <Tag>Aucun rôle</Tag>
                : user.roles.map((r) => <Tag key={r} color={ROLE_COLORS[r] ?? 'default'}>{r}</Tag>)}
            </Descriptions.Item>
            <Descriptions.Item label="Organisation(s)">
              {user.organizations.length === 0 ? '—' : user.organizations.map((o) => o.name).join(', ')}
            </Descriptions.Item>
            <Descriptions.Item label="Dernière connexion">
              {user.last_login_at ? new Date(user.last_login_at).toLocaleString('fr-TN') : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Créé le">{new Date(user.created_at).toLocaleString('fr-TN')}</Descriptions.Item>
          </Descriptions>
        </Card>

        {canUpdate && isPrivileged && (
          <Alert
            style={{ marginTop: 16 }}
            type="info"
            showIcon
            message="Compte à rôle privilégié"
            description="Le rôle, le statut et les organisations d'un compte Super Administrator, Exploitant ou Finance ne peuvent pas être modifiés depuis cet écran. Utilisez l'outil de provisionnement en ligne de commande."
          />
        )}

        {canUpdate && !isPrivileged && (
          <Card className="station-info-card" title="Modifier le rôle" style={{ marginTop: 16 }}>
            <Space direction="vertical" style={{ width: '100%' }}>
              <Select
                style={{ width: 280 }}
                value={selectedRole}
                onChange={setSelectedRole}
                allowClear
                placeholder="Aucun rôle"
                options={ASSIGNABLE_ROLES.map((r) => ({ value: r, label: r }))}
              />
              <Button
                type="primary"
                onClick={handleAssignRole}
                loading={assignRoleMutation.isPending}
                disabled={!selectedRole || selectedRole === currentRole}
              >
                Enregistrer le rôle
              </Button>
            </Space>
          </Card>
        )}

        {canUpdate && !isPrivileged && (
          <Card className="station-info-card" title="Gérer les organisations" style={{ marginTop: 16 }}>
            <Space direction="vertical" style={{ width: '100%' }}>
              <Select
                mode="multiple"
                style={{ width: '100%' }}
                value={selectedOrgIds}
                onChange={setSelectedOrgIds}
                placeholder="Sélectionner une ou plusieurs organisations"
                options={(organizations ?? []).map((o) => ({ value: o.id, label: o.name }))}
              />
              {currentRole === 'Client' && (
                <Alert type="warning" showIcon message="Un utilisateur Client doit conserver au moins une organisation." />
              )}
              <Button
                type="primary"
                onClick={handleSyncOrganizations}
                loading={syncOrgsMutation.isPending}
              >
                Enregistrer les organisations
              </Button>
            </Space>
          </Card>
        )}

        <Divider />

        <Space wrap>
          {canDisable && !isPrivileged && (
            <Popconfirm
              title={user.account_status === 'active' ? 'Désactiver ce compte ?' : 'Activer ce compte ?'}
              onConfirm={toggleAccountStatus}
              okText="Confirmer"
              cancelText="Annuler"
            >
              <Button danger={user.account_status === 'active'} loading={statusMutation.isPending}>
                {user.account_status === 'active' ? 'Désactiver le compte' : 'Activer le compte'}
              </Button>
            </Popconfirm>
          )}
          {canUpdate && (
            <Button onClick={handleResend} loading={resendMutation.isPending}>
              Renvoyer le lien de configuration
            </Button>
          )}
        </Space>
      </main>
    </AppShell>
  );
}
