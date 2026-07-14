import { expect, test } from '@playwright/test';
import { callRoute, isProtectedRoute, loadActiveApiInventory } from './support/active-api';

const protectedRoutes = loadActiveApiInventory().usedRoutes.filter(isProtectedRoute);

test.describe('Active API authentication contract', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(15_000);

  for (const route of protectedRoutes) {
    test(`${route.method} /${route.uri} rejects an unauthenticated request`, async ({ request }) => {
      const response = await callRoute(request, route);
      expect(response.status(), `${route.method} /${route.uri}`).toBe(401);
    });
  }
});
