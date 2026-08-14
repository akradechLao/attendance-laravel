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
    path: '/employee/history',
    name: 'EmployeeHistory',
    component: () => import('./pages/Employee/EmployeeHistory.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/login',
    name: 'AdminLogin',
    component: () => import('./pages/Auth/AdminLogin.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/employee/menu',
    name: 'EmployeeMenu',
    component: () => import('./pages/Employee/EmployeeMenu.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/leave',
    name: 'EmployeeLeaveRequest',
    component: () => import('./pages/Employee/LeaveRequest.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/ot',
    name: 'EmployeeOtRequest',
    component: () => import('./pages/Employee/OtRequest.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/wfh',
    name: 'EmployeeWfhRequest',
    component: () => import('./pages/Employee/WfhRequest.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/change-password',
    name: 'EmployeeChangePassword',
    component: () => import('./pages/Employee/ChangePassword.vue'),
    meta: { requiresAuth: true }
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
  },
  {
    path: '/wfh',
    name: 'WfhManagement',
    component: () => import('./pages/HR/WfhManagement.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/holidays',
    name: 'HolidayManagement',
    component: () => import('./pages/HR/HolidayManagement.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/shifts',
    name: 'ShiftManagement',
    component: () => import('./pages/HR/ShiftManagement.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/shift-assignments',
    name: 'ShiftAssignment',
    component: () => import('./pages/HR/ShiftAssignment.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/mandatory-ot',
    name: 'MandatoryOt',
    component: () => import('./pages/HR/MandatoryOt.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/attendance-adjustment',
    name: 'AttendanceAdjustment',
    component: () => import('./pages/HR/AttendanceAdjustment.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/attendance-verification',
    name: 'AttendanceVerification',
    component: () => import('./pages/HR/AttendanceVerification.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/auto-ot',
    name: 'AutoOt',
    component: () => import('./pages/HR/AutoOt.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/photos',
    name: 'PhotoHistory',
    component: () => import('./pages/HR/PhotoHistory.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/photo-import',
    name: 'PhotoImport',
    component: () => import('./pages/HR/PhotoImport.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/supervisor/leave-approval',
    name: 'SupervisorLeaveApproval',
    component: () => import('./pages/Supervisor/LeaveApproval.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/supervisor/ot-approval',
    name: 'SupervisorOtApproval',
    component: () => import('./pages/Supervisor/OtApproval.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/supervisor/team-calendar',
    name: 'SupervisorTeamCalendar',
    component: () => import('./pages/Supervisor/TeamCalendar.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/manager/leave-approval',
    name: 'ManagerLeaveApproval',
    component: () => import('./pages/Manager/LeaveApproval.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/manager/ot-approval',
    name: 'ManagerOtApproval',
    component: () => import('./pages/Manager/OtApproval.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/manager/team-report',
    name: 'ManagerTeamReport',
    component: () => import('./pages/Manager/TeamReport.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/admin/company-settings',
    name: 'CompanySettings',
    component: () => import('./pages/Admin/CompanySettings.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/admin/system-settings',
    name: 'SystemSettings',
    component: () => import('./pages/Admin/SystemSettings.vue'),
    meta: { requiresAuth: true, layout: 'app' }
  },
  {
    path: '/admin/location-settings',
    name: 'LocationSettings',
    component: () => import('./pages/Admin/LocationSettings.vue'),
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
