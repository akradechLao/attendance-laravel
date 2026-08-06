import { reactive } from 'vue'

const state = reactive({
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  token: localStorage.getItem('token') || '',
  companies: []
})

export function setCurrentUser(user) {
  state.user = user
  localStorage.setItem('user', JSON.stringify(user))
}

export function setToken(token) {
  state.token = token
  localStorage.setItem('token', token)
}

export function setCompanies(companies) {
  state.companies = companies
}

export function logout() {
  state.user = null
  state.token = ''
  localStorage.removeItem('user')
  localStorage.removeItem('token')
}

export default state
