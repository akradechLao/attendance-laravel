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
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('./pages/Auth/ForgotPassword.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/admin/profile',
    name: 'AdminProfile',
    component: () => import('./pages/Auth/AdminProfile.vue'),
    meta: { requiresAuth: true }
  },
  // ---- Employee Self-Service (any authenticated user) ----
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
    path: '/employee/profile',
    name: 'EmployeeProfile',
    component: () => import('./pages/Employee/EmployeeProfile.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/schedule',
    name: 'EmployeeSchedule',
    component: () => import('./pages/Employee/EmployeeSchedule.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/announcements',
    name: 'EmployeeAnnouncements',
    component: () => import('./pages/Employee/Announcement.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/stats',
    name: 'EmployeeStats',
    component: () => import('./pages/Employee/LateWarning.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/shift-swap',
    name: 'EmployeeShiftSwap',
    component: () => import('./pages/Employee/ShiftSwap.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/shift-request',
    name: 'EmployeeShiftRequest',
    component: () => import('./pages/Employee/EmployeeShiftRequest.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/holidays',
    name: 'EmployeeHolidays',
    component: () => import('./pages/Employee/HolidayCalendar.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/team-leave',
    name: 'SupervisorLeaveCalendar',
    component: () => import('./pages/Employee/SupervisorLeaveCalendar.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/dashboard',
    name: 'EmployeeDashboard',
    component: () => import('./pages/Employee/EmployeeDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employee/payslip',
    name: 'EmployeePayslip',
    component: () => import('./pages/Employee/Payslip.vue'),
    meta: { requiresAuth: true }
  },
  // ---- HR Routes (admin + super_admin) ----
  {
    path: '/payslip-entry',
    name: 'PayslipEntry',
    component: () => import('./pages/HR/PayslipEntry.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('./pages/HR/Dashboard.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/employees',
    name: 'EmployeeList',
    component: () => import('./pages/HR/EmployeeList.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/employees/:id/face',
    name: 'FaceRegistration',
    component: () => import('./pages/HR/FaceRegistration.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('./pages/HR/Reports.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/audit-log',
    name: 'AuditLog',
    component: () => import('./pages/HR/AuditLog.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('./pages/HR/Settings.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/leave',
    name: 'LeaveManagement',
    component: () => import('./pages/HR/LeaveManagement.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/ot',
    name: 'OtManagement',
    component: () => import('./pages/HR/OtManagement.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/leave-approval',
    name: 'HrLeaveApproval',
    component: () => import('./pages/HR/LeaveApproval.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/wfh-approval',
    name: 'HrWfhApproval',
    component: () => import('./pages/HR/WfhApproval.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/shift-swap-approval',
    name: 'HrShiftSwapApproval',
    component: () => import('./pages/HR/ShiftSwapApproval.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/shift-request-approval',
    name: 'HrShiftRequestApproval',
    component: () => import('./pages/HR/ShiftRequestApproval.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/remote-assignments',
    name: 'RemoteAssignment',
    component: () => import('./pages/HR/RemoteAssignment.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/location-history',
    name: 'LocationHistory',
    component: () => import('./pages/HR/LocationHistory.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/wfh',
    name: 'WfhManagement',
    component: () => import('./pages/HR/WfhManagement.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/holidays',
    name: 'HolidayManagement',
    component: () => import('./pages/HR/HolidayManagement.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/shifts',
    name: 'ShiftManagement',
    component: () => import('./pages/HR/ShiftManagement.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/shift-assignments',
    name: 'ShiftAssignment',
    component: () => import('./pages/HR/ShiftAssignment.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/mandatory-ot',
    name: 'MandatoryOt',
    component: () => import('./pages/HR/MandatoryOt.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/attendance-adjustment',
    name: 'AttendanceAdjustment',
    component: () => import('./pages/HR/AttendanceAdjustment.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/manual-entry',
    name: 'ManualEntry',
    component: () => import('./pages/HR/ManualEntry.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/auto-ot',
    name: 'AutoOt',
    component: () => import('./pages/HR/AutoOt.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/photos',
    name: 'PhotoHistory',
    component: () => import('./pages/HR/PhotoHistory.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/photo-import',
    name: 'PhotoImport',
    component: () => import('./pages/HR/PhotoImport.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  {
    path: '/ot-summary',
    name: 'OtSummary',
    component: () => import('./pages/OtSummary.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'admin' }
  },
  // ---- Supervisor/Manager Routes ----
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
  // ---- Super Admin Routes ----
  {
    path: '/permission',
    name: 'Permission',
    component: () => import('./pages/Permission.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'super_admin' }
  },
  {
    path: '/admin/company-settings',
    name: 'CompanySettings',
    component: () => import('./pages/Admin/CompanySettings.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'super_admin' }
  },
  {
    path: '/admin/system-settings',
    name: 'SystemSettings',
    component: () => import('./pages/Admin/SystemSettings.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'super_admin' }
  },
  {
    path: '/admin/location-settings',
    name: 'LocationSettings',
    component: () => import('./pages/Admin/LocationSettings.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'super_admin' }
  },
  {
    path: '/telegram-settings',
    name: 'TelegramSettings',
    component: () => import('./pages/Employee/TelegramSettings.vue'),
    meta: { requiresAuth: true, layout: 'app', requiresRole: 'super_admin' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

const roleHierarchy = {
  employee: 1,
  admin: 2,
  super_admin: 3
}

router.beforeEach((to, from, next) => {
  const isAuthenticated = !!store.token
  const userRole = store.user?.role || 'employee'

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
    return
  }

  if (to.meta.requiresRole) {
    const requiredRole = to.meta.requiresRole
    const userLevel = roleHierarchy[userRole] || 0
    const requiredLevel = roleHierarchy[requiredRole] || 0

    if (userLevel < requiredLevel) {
      if (userRole === 'employee') {
        next('/employee/menu')
      } else {
        next('/dashboard')
      }
      return
    }
  }

  next()
})

export default router
