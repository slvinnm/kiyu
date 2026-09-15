export type Department = {
  id: number
  code: string
  name: string
}

export type QueueTicket = {
  id: number
  queue_number: string
  status: string
  priority: number
  station_id: number
}

export type Visit = {
  id: number
  visit_number: string
}

export type QueueAcquisition = {
  id: number
  status: string
  channel: string
  acquired_at: string
  department: Department
  visit: Visit
  queue_ticket: QueueTicket
}

export type ApiSuccessResponse<T> = {
  success: true
  message: string
  data: T
}

export type ApiErrorResponse = {
  success?: false
  message?: string
  errors?: Record<string, string[]>
}

export type KioskState =
  | { type: 'idle' }
  | { type: 'loading'; departmentCode: string }
  | {
      type: 'success'
      acquisition: QueueAcquisition
      message: string
    }
  | {
      type: 'error'
      message: string
      departmentCode?: string
    }
