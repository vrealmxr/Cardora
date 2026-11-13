import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App.jsx'
import './index.css'

const container = document.getElementById('root')
const snapshotNode = document.getElementById('cardora-public-data')
let snapshot
try {
  const candidate = snapshotNode ? JSON.parse(snapshotNode.textContent) : null
  if (candidate?.version === 1 && candidate.path === window.location.pathname + window.location.search) snapshot = candidate
} catch { /* A malformed snapshot falls back to the normal client application. */ }

const application = (
  <React.StrictMode>
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <App initialPublicData={snapshot?.publicData} initialSeo={snapshot?.seo} />
    </BrowserRouter>
  </React.StrictMode>
)

if (snapshot) ReactDOM.hydrateRoot(container, application)
else ReactDOM.createRoot(container).render(application)

