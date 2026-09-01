export type FoundationContext = {
  tenant_id: number | null;
  company_id: number | null;
  branch_id: number | null;
  role?: string;
  permissions: string[];
};

export type Membership = {
  id: number;
  tenant_id: number;
  user_id: number;
  company_id: number | null;
  branch_id: number | null;
  role_id: number;
  status: string;
  is_primary: boolean;
};
