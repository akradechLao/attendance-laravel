import { createRouter, createWebHistory } from 'vue-router'
import store from './store'

const routes = [
  {
    path: '/',
    redirect: '/employee'
  },
  {
    path: '/employee',
    name: 'Kiosk',
    component: () => import('./pages/Employee/Kiosk.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/login',
    name: 'AdminLogin',
    component: () => import('./pages/Auth/AdminLogin.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('./pages/HR/Dashboard.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/employees',
    name: 'EmployeeList',
    component: () => import('./pages/HR/EmployeeList.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/employees/:id/face',
    name: 'FaceRegistration',
    component: () => import('./pages/HR/FaceRegistration.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('./pages/HR/Reports.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('./pages/HR/Settings.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/leave',
    name: 'LeaveManagement',
    component: () => import('./pages/HR/LeaveManagement.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/ot',
    name: 'OtManagement',
    component: () => import('./pages/HR/OtManagement.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/remote-assignments',
    name: 'RemoteAssignment',
    component: () => import('./pages/HR/RemoteAssignment.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/location-history',
    name: 'LocationHistory',
    component: () => import('./pages/HR/LocationHistory.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const isAuthenticated = !!store.token
  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else {
    next()
  }
})

export default router