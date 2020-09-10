import Vue from 'vue';

export default {
  state: {
    authPlugin: 'dummy',
    id: null,
    name: null,
    user: {},
    loggedIn: false,
    loggedInWith: '',
    appUrl: '',
  },
  actions: {
    authorize(context, user) {
      context.commit('setUser', user);
      Vue.prototype.$log.trackEventLoggedIn(user);
    },
    logout(context) {
      context.commit('unsetUser');
    },
    setAuthPlugin(context, plugin) {
      context.commit('setAuthPlugin', plugin);
    },
    setAppUrl(context, appUrl) {
      context.commit('setAppUrl', appUrl);
    },
  },
  mutations: {
    setUser(state, user) {
      state.user = user;
      state.loggedIn = true;
      state.loggedInWith = state.authPlugin;
    },
    unsetUser(state) {
      state.user = {};
      state.loggedIn = false;
    },
    setAuthPlugin(state, plugin) {
      state.authPlugin = plugin;
    },
    setAppUrl(state, appUrl) {
      state.appUrl = appUrl;
    },
  },
  getters: {
    isLoggedIn: (state) => {
      if (state.authPlugin !== state.loggedInWith) {
        return false;
      }

      return state.loggedIn;
    },
    authPlugin: (state) => state.authPlugin,
    getAppUrl: (state) => state.appUrl,
    getUser: (state) => state.user,
  },
};
