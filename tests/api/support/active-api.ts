import fs from 'node:fs';
import path from 'node:path';
import type { APIRequestContext, APIResponse } from '@playwright/test';

export interface ActiveApiRoute {
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  uri: string;
  action: string;
  middleware: string[];
}

interface ActiveApiInventory {
  generatedAt: string;
  usedRoutes: ActiveApiRoute[];
  unmatchedClientCalls: Array<{
    method: ActiveApiRoute['method'];
    clientPath: string;
    source: string;
    line: number;
  }>;
}

const root = process.cwd();
const inventoryPath = path.join(root, 'storage', 'api-test-reports', 'inventory', 'used-api-inventory.json');

export const API_BASE_URL = (process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');

export function loadActiveApiInventory(): ActiveApiInventory {
  if (!fs.existsSync(inventoryPath)) {
    throw new Error(`Active API inventory is missing. Run the inventory script first: ${inventoryPath}`);
  }

  return JSON.parse(fs.readFileSync(inventoryPath, 'utf8')) as ActiveApiInventory;
}

export function materializeRoute(uri: string): string {
  return uri
    .replace(/\/\{[^}]+\?\}/g, '')
    .replace(/\{[^}]+\}/g, '999999999');
}

export function isProtectedRoute(route: ActiveApiRoute): boolean {
  return route.middleware.some((middleware) => middleware.includes('Authenticate:sanctum'));
}

export async function callRoute(
  request: APIRequestContext,
  route: ActiveApiRoute,
  headers: Record<string, string> = {},
  params: Record<string, string | number | boolean> = {},
): Promise<APIResponse> {
  const url = `${API_BASE_URL}/${materializeRoute(route.uri)}`;
  const options: Parameters<APIRequestContext['fetch']>[1] = {
    method: route.method,
    headers: {
      Accept: 'application/json',
      ...headers,
    },
    params,
    failOnStatusCode: false,
  };

  if (!['GET', 'DELETE'].includes(route.method)) options.data = {};
  return request.fetch(url, options);
}
